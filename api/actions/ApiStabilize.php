<?php
/**
 * Created on Sep 19, 2009
 *
 * API module for MediaWiki's FlaggedRevs extension
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */

/**
 * API module to stabilize pages
 *
 * @ingroup FlaggedRevs
 */
abstract class ApiStabilize extends ApiBase {
	use ApiWatchlistTrait;

	/** @var Title|null */
	protected $title;

	/**
	 * @param ApiMain $mainModule
	 * @param string $moduleName
	 * @param string $modulePrefix
	 */
	public function __construct( ApiMain $mainModule, $moduleName, $modulePrefix = '' ) {
		parent::__construct( $mainModule, $moduleName, $modulePrefix );

		// Variables provided by ApiWatchlistTrait. But, watchlist handling is done within
		// the form objects that the Api modules create, and those don't yet support watchlist
		// expiration.
		// $this->watchlistExpiryEnabled = $this->getConfig()->get( 'WatchlistExpiry' );
		$this->watchlistExpiryEnabled = false;

		// Need to provide some value, might as well be the correct one
		$this->watchlistMaxDuration = $this->getConfig()->get( 'WatchlistExpiryMaxDuration' );
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		$params = $this->extractRequestParams();
		$user = $this->getUser();

		$this->title = Title::newFromText( $params['title'] );
		if ( $this->title == null ) {
			$this->dieWithError(
				[ 'apierror-invalidtitle', wfEscapeWikiText( $params['title'] ) ]
			);
		}

		$errors = $this->getPermissionManager()
			->getPermissionErrors( 'stablesettings', $user, $this->title );
		if ( $errors ) {
			$this->dieStatus( $this->errorArrayToStatus( $errors, $user ) );
		}

		$this->doExecute(); // child class
	}

	abstract public function doExecute();

	/**
	 * @inheritDoc
	 */
	public function isWriteMode() {
		return true;
	}

	/**
	 * @return string
	 */
	public function needsToken() {
		return 'csrf';
	}
}
