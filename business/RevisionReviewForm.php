<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;

/**
 * Class containing revision review form business logic
 */
class RevisionReviewForm extends FRGenericSubmitForm {

	public const ACTION_APPROVE = 'approve';
	public const ACTION_UNAPPROVE = 'unapprove';
	public const ACTION_REJECT = 'reject';

	/** @var Title|null Target title object */
	private $page = null;
	/** @var FlaggableWikiPage|null Target page object */
	private $article = null;
	/** @var string|null One of the self::ACTION_… constants */
	private $action = null;
	/** @var int ID being reviewed (last "bad" ID for rejection) */
	private $oldid = 0;
	/** @var int Old, "last good", ID (used for rejection) */
	private $refid = 0;
	/** @var string Included template versions (flat string) */
	private $templateParams = '';
	/** @var string Parameter key */
	private $validatedParams = '';
	/** @var string Review comments */
	private $comment = '';
	/** Review tag (for approval) */
	private ?int $tag = null;
	/** @var string|null Conflict handling */
	private $lastChangeTime = null;
	/** @var string|null Conflict handling */
	private $newLastChangeTime = null;

	/** @var FlaggedRevision|null Prior FlaggedRevision for Rev with ID $oldid */
	private $oldFrev = null;

	/** @var string User session key */
	private $sessionKey = '';
	/** @var bool Skip validatedParams check */
	private $skipValidationKey = false;

	protected function initialize() {
		if ( FlaggedRevs::useOnlyIfProtected() ) {
			$this->tag = 0; // default to "inadequate"
		}
	}

	/**
	 * @return Title|null
	 */
	public function getPage() {
		return $this->page;
	}

	/**
	 * @param Title $value
	 */
	public function setPage( Title $value ) {
		$this->trySet( $this->page, $value );
	}

	/**
	 * @param string $action One of the self::ACTION… constants
	 */
	public function setAction( string $action ) {
		$this->trySet( $this->action, $action );
	}

	/**
	 * @param string|null $value
	 */
	public function setLastChangeTime( $value ) {
		$this->trySet( $this->lastChangeTime, $value );
	}

	/**
	 * @return string|null
	 */
	public function getNewLastChangeTime() {
		return $this->newLastChangeTime;
	}

	/**
	 * @return int
	 */
	public function getRefId() {
		return $this->refid;
	}

	/**
	 * @param int $value
	 */
	public function setRefId( $value ) {
		$this->trySet( $this->refid, (int)$value );
	}

	/**
	 * @return int
	 */
	public function getOldId() {
		return $this->oldid;
	}

	/**
	 * @param int $value
	 */
	public function setOldId( $value ) {
		$this->trySet( $this->oldid, (int)$value );
	}

	/**
	 * @param string $value
	 */
	public function setTemplateParams( $value ) {
		$this->trySet( $this->templateParams, $value );
	}

	/**
	 * @param string $value
	 */
	public function setValidatedParams( $value ) {
		$this->trySet( $this->validatedParams, $value );
	}

	/**
	 * @return string
	 */
	public function getComment() {
		return $this->comment;
	}

	/**
	 * @param string $value
	 */
	public function setComment( $value ) {
		$this->trySet( $this->comment, $value );
	}

	/**
	 * @param int $value
	 * @deprecated Use setTag() instead.
	 */
	public function setDim( $value ) {
		$this->setTag( (int)$value );
	}

	public function setTag( int $value ): void {
		if ( !FlaggedRevs::useOnlyIfProtected() ) {
			$this->trySet( $this->tag, $value );
		}
	}

	/**
	 * Get tags array, for usage with code that expects an array of tags
	 * rather than a single tag.
	 * @return array<string,int>
	 */
	private function getTags(): array {
		return $this->tag !== null ? [ FlaggedRevs::getTagName() => $this->tag ] : [];
	}

	/**
	 * @param string $sessionId
	 */
	public function setSessionKey( $sessionId ) {
		$this->sessionKey = $sessionId;
	}

	public function bypassValidationKey() {
		$this->skipValidationKey = true;
	}

	/**
	 * Check that a target is given (e.g. from GET/POST request)
	 * @return true|string true on success, error string on failure
	 */
	protected function doCheckTargetGiven() {
		if ( $this->page === null ) {
			return 'review_page_invalid';
		}
		return true;
	}

	/**
	 * Load any objects after ready() called
	 */
	protected function doBuildOnReady() {
		$this->article = FlaggableWikiPage::getTitleInstance( $this->page );
	}

	/**
	 * Check that the target is valid (e.g. from GET/POST request)
	 * @param int $flags FOR_SUBMISSION (set on submit)
	 * @return true|string true on success, error string on failure
	 */
	protected function doCheckTarget( $flags = 0 ) {
		$flgs = ( $flags & self::FOR_SUBMISSION ) ? Title::READ_LATEST : 0;
		if ( !$this->page->getArticleID( $flgs ) ) {
			return 'review_page_notexists';
		}
		if ( !$this->article->isReviewable() ) {
			return 'review_page_unreviewable';
		}
		return true;
	}

	/**
	 * Validate and clean up parameters (e.g. from POST request).
	 * @return true|string true on success, error string on failure
	 */
	protected function doCheckParameters() {
		$action = $this->getAction();
		if ( $action === null ) {
			return 'review_param_missing'; // no action specified (approve, reject, de-approve)
		} elseif ( !$this->oldid ) {
			return 'review_no_oldid'; // no revision target
		}
		# Get the revision's current flags (if any)
		$this->oldFrev = FlaggedRevision::newFromTitle( $this->page, $this->oldid, IDBAccessObject::READ_LATEST );
		$oldTag = $this->oldFrev ? $this->oldFrev->getTag() : FlaggedRevision::getDefaultTag();
		# Set initial value for newLastChangeTime (if unchanged on submit)
		$this->newLastChangeTime = $this->lastChangeTime;
		# Fill in implicit tag for binary flag case
		if ( FlaggedRevs::binaryFlagging() ) {
			if ( $this->action === self::ACTION_APPROVE ) {
				$this->tag = 1;
			} elseif ( $this->action === self::ACTION_UNAPPROVE ) {
				$this->tag = 0;
			}
		}
		if ( $action === self::ACTION_APPROVE ) {
			# The tag should not be zero
			if ( $this->tag === 0 ) {
				return 'review_too_low';
			}
			# Special token to discourage fiddling with templates...
			if ( !$this->skipValidationKey ) {
				$k = self::validationKey( $this->templateParams, $this->oldid, $this->sessionKey );
				if ( $this->validatedParams !== $k ) {
					return 'review_bad_key';
				}
			}
			# Sanity check tag
			if ( !FlaggedRevs::tagIsValid( $this->tag ) ) {
				return 'review_bad_tags';
			}
			# Check permissions with tag
			if ( !FlaggedRevs::userCanSetTag( $this->user, $this->tag, $oldTag ) ) {
				return 'review_denied';
			}
		} elseif ( $action === self::ACTION_UNAPPROVE ) {
			# Check permissions with old tag
			if ( !FlaggedRevs::userCanSetTag( $this->user, $oldTag ) ) {
				return 'review_denied';
			}
		}
		return true;
	}

	private function isAllowed() {
		// Basic permission check
		return ( $this->page && MediaWikiServices::getInstance()->getPermissionManager()
			->userCan( 'review', $this->user, $this->page ) );
	}

	/**
	 * Get the action this submission is requesting
	 * @return string|null (approve,unapprove,reject)
	 */
	public function getAction(): ?string {
		return $this->action;
	}

	/**
	 * Submit the form parameters for the page config to the DB.
	 *
	 * @return true|string true on success, error string on failure
	 */
	protected function doSubmit() {
		# Double-check permissions
		if ( !$this->isAllowed() ) {
			return 'review_denied';
		}
		$user = $this->user;
		# We can only approve actual revisions...
		$services = MediaWikiServices::getInstance();
		$revStore = $services->getRevisionStore();
		if ( $this->getAction() === self::ACTION_APPROVE ) {
			$revRecord = $revStore->getRevisionByTitle( $this->page, $this->oldid );
			# Check for archived/deleted revisions...
			if ( !$revRecord || $revRecord->isDeleted( RevisionRecord::DELETED_TEXT ) ) {
				return 'review_bad_oldid';
			}
			# Check for review conflicts...
			if ( $this->lastChangeTime !== null ) { // API uses null
				$lastChange = $this->oldFrev ? $this->oldFrev->getTimestamp() : '';
				if ( $lastChange !== $this->lastChangeTime ) {
					return 'review_conflict_oldid';
				}
			}
			$this->approveRevision( $revRecord, $this->oldFrev );
			$status = true;
		# We can only unapprove approved revisions...
		} elseif ( $this->getAction() === self::ACTION_UNAPPROVE ) {
			# Check for review conflicts...
			if ( $this->lastChangeTime !== null ) { // API uses null
				$lastChange = $this->oldFrev ? $this->oldFrev->getTimestamp() : '';
				if ( $lastChange !== $this->lastChangeTime ) {
					return 'review_conflict_oldid';
				}
			}
			# Check if we can find this flagged rev...
			if ( !$this->oldFrev ) {
				return 'review_not_flagged';
			}
			$this->unapproveRevision( $this->oldFrev );
			$status = true;
		} elseif ( $this->getAction() === self::ACTION_REJECT ) {
			$newRevRecord = $revStore->getRevisionByTitle( $this->page, $this->oldid );
			$oldRevRecord = $revStore->getRevisionByTitle( $this->page, $this->refid );
			# Do not mess with archived/deleted revisions
			if ( !$oldRevRecord ||
				$oldRevRecord->isDeleted( RevisionRecord::DELETED_TEXT ) ||
				!$newRevRecord ||
				$newRevRecord->isDeleted( RevisionRecord::DELETED_TEXT )
			) {
				return 'review_bad_oldid';
			}
			# Check that the revs are in order
			if ( $oldRevRecord->getTimestamp() > $newRevRecord->getTimestamp() ) {
				return 'review_cannot_undo';
			}
			# Make sure we are only rejecting pending changes
			$srev = FlaggedRevision::newFromStable( $this->page, IDBAccessObject::READ_LATEST );
			if ( $srev && $oldRevRecord->getTimestamp() < $srev->getRevTimestamp() ) {
				return 'review_cannot_reject'; // not really a use case
			}
			$article = $services->getWikiPageFactory()->newFromTitle( $this->page );
			# Get text with changes after $oldRev up to and including $newRev removed
			if ( WikiPage::hasDifferencesOutsideMainSlot( $newRevRecord, $oldRevRecord ) ) {
				return 'review_cannot_undo';
			}
			$undoHandler = $services->getContentHandlerFactory()
				->getContentHandler(
					$newRevRecord->getSlot( SlotRecord::MAIN )->getModel()
				);
			$currentContent = $article->getRevisionRecord()->getContent( SlotRecord::MAIN );
			$undoContent = $newRevRecord->getContent( SlotRecord::MAIN );
			$undoAfterContent = $oldRevRecord->getContent( SlotRecord::MAIN );
			if ( !$currentContent || !$undoContent || !$undoAfterContent ) {
				return 'review_cannot_undo';
			}
			$new_content = $undoHandler->getUndoContent(
				$currentContent,
				$undoContent,
				$undoAfterContent,
				$newRevRecord->isCurrent()
			);
			if ( $new_content === false ) {
				return 'review_cannot_undo';
			}

			$baseRevId = $newRevRecord->isCurrent() ? $oldRevRecord->getId() : 0;

			$comment = $this->getComment();

			// Actually make the edit...
			// Note: this should be changed to use the $undidRevId parameter so that the
			// edit is properly marked as an undo. Do this only after T153570 is merged
			// into Echo, otherwise we would get duplicate revert notifications.
			$editStatus = $article->doUserEditContent(
				$new_content,
				$user,
				$comment,
				0, // flags
				$baseRevId
			);

			$status = $editStatus->isOK() ? true : 'review_cannot_undo';

			// Notify Echo about the revert.
			// This is due to the lack of appropriate EditResult handling in Echo, in the
			// future, when T153570 is merged, this entire code block should be removed.
			if ( $status === true &&
				// @phan-suppress-next-line PhanTypeArraySuspiciousNullable
				$editStatus->value['revision-record'] &&
				ExtensionRegistry::getInstance()->isLoaded( 'Echo' )
			) {
				$affectedRevisions = []; // revid -> userid
				$revQuery = $revStore->getQueryInfo();
				$dbr = wfGetDB( DB_REPLICA );
				$revisions = $dbr->select(
					$revQuery['tables'],
					[ 'rev_id', 'rev_user' => $revQuery['fields']['rev_user'] ],
					[
						'rev_id <= ' . $newRevRecord->getId(),
						'rev_timestamp <= ' . $dbr->addQuotes( $dbr->timestamp( $newRevRecord->getTimestamp() ) ),
						'rev_id > ' . $oldRevRecord->getId(),
						'rev_timestamp > ' . $dbr->addQuotes( $dbr->timestamp( $oldRevRecord->getTimestamp() ) ),
						'rev_page' => $article->getId(),
					],
					__METHOD__,
					[],
					$revQuery['joins']
				);
				foreach ( $revisions as $row ) {
					$affectedRevisions[$row->rev_id] = $row->rev_user;
				}

				EchoEvent::create( [
					'type' => 'reverted',
					'title' => $this->page,
					'extra' => [
						// @phan-suppress-next-line PhanTypeArraySuspiciousNullable
						'revid' => $editStatus->value['revision-record']->getId(),
						'reverted-users-ids' => array_values( $affectedRevisions ),
						'reverted-revision-ids' => array_keys( $affectedRevisions ),
						'method' => 'flaggedrevs-reject',
					],
					'agent' => $user,
				] );
			}

			# If this undid one edit by another logged-in user, update user tallies
			if ( $status === true
				&& $newRevRecord->getParentId() == $oldRevRecord->getId()
				&& $newRevRecord->getUser( RevisionRecord::RAW )
				&& $newRevRecord->getUser( RevisionRecord::RAW )->isRegistered()
				&& !$newRevRecord->getUser( RevisionRecord::RAW )->equals( $user ) // no self-reverts
			) {
				FRUserCounters::incCount(
					$newRevRecord->getUser( RevisionRecord::RAW )->getId(),
					'revertedEdits'
				);
			}
		} else {
			return 'review_param_missing';
		}
		# Watch page if set to do so
		if ( $status === true ) {
			$userOptionsLookup = $services->getUserOptionsLookup();
			$watchlistManager = $services->getWatchlistManager();
			if ( $userOptionsLookup->getOption( $user, 'flaggedrevswatch' ) &&
				!$watchlistManager->isWatched( $user, $this->page ) ) {
				$watchlistManager->addWatch( $user, $this->page );
			}
		}

		( new FlaggedRevsHookRunner( $services->getHookContainer() ) )->onFlaggedRevsRevisionReviewFormAfterDoSubmit(
			$this,
			$status
		);

		return $status;
	}

	/**
	 * Adds or updates the flagged revision table for this page/id set
	 * @param RevisionRecord $revRecord The revision to be accepted
	 * @param FlaggedRevision|null $oldFrev Currently accepted version of $rev or null
	 */
	private function approveRevision(
		RevisionRecord $revRecord,
		FlaggedRevision $oldFrev = null
	) {
		# Revision rating flags
		$flags = $this->getTags();
		# Our template version pointers
		$tmpVersions = $this->getIncludeVersions( $this->templateParams );
		# Get current stable version ID (for logging)
		$oldSv = FlaggedRevision::newFromStable( $this->page, IDBAccessObject::READ_LATEST );

		# Is this a duplicate review?
		if ( $oldFrev &&
			$oldFrev->getTags() == $flags && // tags => quality
			$oldFrev->getTemplateVersions( IDBAccessObject::READ_LATEST ) == $tmpVersions
		) {
			return; // don't record if the same
		}

		# The new review entry...
		$flaggedRevision = new FlaggedRevision( [
			'revrecord'         => $revRecord,
			'user_id'           => $this->user->getId(),
			'timestamp'         => wfTimestampNow(),
			'tags'              => $flags,
			'templateVersions'  => $tmpVersions,
			'flags'             => ''
		] );
		# Delete the old review entry if it exists...
		if ( $oldFrev ) {
			$oldFrev->delete();
		}
		# Insert the new review entry...
		$status = $flaggedRevision->insert();
		if ( $status !== true ) {
			throw new UnexpectedValueException(
				'Flagged revision with ID ' .
				(string)$revRecord->getId() .
				' exists with unexpected fr_page_id, error: ' . $status
			);
		}

		$flaggedRevision->approveRevertedTagUpdate();

		# Update the article review log...
		$oldSvId = $oldSv ? $oldSv->getRevId() : 0;
		FlaggedRevsLog::updateReviewLog( $this->page, $this->getTags(),
			$this->comment, $this->oldid, $oldSvId, true, $this->user );

		# Get the new stable version as of now
		$sv = FlaggedRevision::determineStable( $this->page );
		# Update recent changes...
		self::updateRecentChanges( $revRecord, 'patrol', $sv );
		# Update page and tracking tables and clear cache
		$changed = FlaggedRevs::stableVersionUpdates( $this->page, $sv, $oldSv );
		if ( $changed ) {
			FlaggedRevs::updateHtmlCaches( $this->page ); // purge pages that use this page
		}

		# Caller may want to get the change time
		$this->newLastChangeTime = $flaggedRevision->getTimestamp();
	}

	/**
	 * @param FlaggedRevision $frev
	 * Removes flagged revision data for this page/id set
	 */
	private function unapproveRevision( FlaggedRevision $frev ) {
		# Get current stable version ID (for logging)
		$oldSv = FlaggedRevision::newFromStable( $this->page, IDBAccessObject::READ_LATEST );

		# Delete from flaggedrevs table
		$frev->delete();

		# Get the new stable version as of now
		$sv = FlaggedRevision::determineStable( $this->page );

		# Update the article review log
		$svId = $sv ? $sv->getRevId() : 0;
		FlaggedRevsLog::updateReviewLog( $this->page, $this->getTags(),
			$this->comment, $this->oldid, $svId, false, $this->user );

		# Update recent changes
		self::updateRecentChanges( $frev->getRevisionRecord(), 'unpatrol', $sv );
		# Update page and tracking tables and clear cache
		$changed = FlaggedRevs::stableVersionUpdates( $this->page, $sv, $oldSv );
		if ( $changed ) {
			FlaggedRevs::updateHtmlCaches( $this->page ); // purge pages that use this page
		}

		# Caller may want to get the change time
		$this->newLastChangeTime = '';
	}

	/**
	 * Get a validation key from template versioning metadata
	 * @param string $tmpP
	 * @param int $revisionId
	 * @param string $sessKey Session key
	 * @return string
	 */
	public static function validationKey( $tmpP, $revisionId, $sessKey ) {
		global $wgSecretKey;
		$key = md5( $wgSecretKey );
		$keyBits = $key[3] . $key[9] . $key[13] . $key[19] . $key[26];
		return md5( $tmpP . $revisionId . $sessKey . $keyBits );
	}

	/**
	 * Update rc_patrolled fields in recent changes after (un)accepting a rev.
	 * This maintains the patrolled <=> reviewed relationship for reviewable namespaces.
	 *
	 * RecentChange should only be passed in when an RC item is saved.
	 *
	 * @param RevisionRecord|RecentChange $rev
	 * @param string $patrol "patrol" or "unpatrol"
	 * @param FlaggedRevision|null $srev The new stable version
	 * @return void
	 */
	public static function updateRecentChanges( $rev, $patrol, $srev ) {
		if ( $rev instanceof RecentChange ) {
			$pageId = $rev->getAttribute( 'rc_cur_id' );
		} else {
			$pageId = $rev->getPageId();
		}
		$sTimestamp = $srev ? $srev->getRevTimestamp() : null;

		$dbw = wfGetDB( DB_PRIMARY );
		$limit = 100; // sanity limit to avoid replica lag (most useful when FR is first enabled)
		$conds = [ 'rc_cur_id' => $pageId ];

		$newPatrolState = null; // set rc_patrolled to this value
		# If we accepted this rev, then mark prior revs as patrolled...
		if ( $patrol === 'patrol' ) {
			if ( $sTimestamp ) { // sanity check; should always be set
				$conds[] = 'rc_timestamp <= ' . $dbw->addQuotes( $dbw->timestamp( $sTimestamp ) );
				$newPatrolState = 1;
			}
		# If we un-accepted this rev, then mark now-pending revs as unpatrolled...
		} elseif ( $patrol === 'unpatrol' ) {
			if ( $sTimestamp ) {
				$conds[] = 'rc_timestamp > ' . $dbw->addQuotes( $dbw->timestamp( $sTimestamp ) );
			}
			$newPatrolState = 0;
		}

		if ( $newPatrolState === null ) {
			return; // leave alone
		}

		// Only update rows that need it
		$conds['rc_patrolled'] = $newPatrolState ? 0 : 1;
		// SELECT and update by PK to avoid lag
		$rcIds = $dbw->selectFieldValues(
			'recentchanges',
			'rc_id',
			$conds,
			__METHOD__,
			[ 'LIMIT' => $limit ]
		);
		if ( $rcIds ) {
			$dbw->update(
				'recentchanges',
				[ 'rc_patrolled' => $newPatrolState ],
				[ 'rc_id' => $rcIds ],
				__METHOD__
			);
		}
	}

	/**
	 * Get template parameters from parser output to use on forms.
	 * @param array<int,array<string,int>> $templateIds {@see ParserOutput::$mTemplateIds} or
	 *  {@see OutputPage::$mTemplateIds}
	 * @return string templateParams
	 */
	public static function getIncludeParams( array $templateIds ) {
		$templateParams = '';
		# NS -> title -> rev ID mapping
		foreach ( $templateIds as $namespace => $t ) {
			foreach ( $t as $dbKey => $revId ) {
				$temptitle = Title::makeTitle( $namespace, $dbKey );
				$templateParams .= $temptitle->getPrefixedDBkey() . "|" . $revId . "#";
			}
		}
		return $templateParams;
	}

	/**
	 * Get template versions from form value for parser output.
	 * @param string $templateParams
	 * @return int[][] {@see ParserOutput::$mTemplateIds} or
	 *    {@see OutputPage::$mTemplateIds}
	 */
	private function getIncludeVersions( $templateParams ) {
		$templateIds = [];
		$templateMap = explode( '#', trim( $templateParams ) );
		foreach ( $templateMap as $template ) {
			if ( !$template ) {
				continue;
			}
			$m = explode( '|', $template, 2 );
			if ( !isset( $m[1] ) || !$m[0] ) {
				continue;
			}
			list( $prefixed_text, $rev_id ) = $m;
			# Get the template title
			$tmp_title = Title::newFromText( $prefixed_text ); // Normalize this to be sure...
			if ( $tmp_title === null ) {
				continue; // Page must be valid!
			}
			$templateIds[$tmp_title->getNamespace()][$tmp_title->getDBkey()] = $rev_id;
		}

		return $templateIds;
	}
}
