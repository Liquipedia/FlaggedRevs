<?php

/**
 * Class containing updater functions for a FlaggedRevs environment
 */
class FlaggedRevsUpdaterHooks {
	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/LoadExtensionSchemaUpdates
	 *
	 * @param DatabaseUpdater $du
	 */
	public static function addSchemaUpdates( DatabaseUpdater $du ) {
		$dbType = $du->getDB()->getType();
		$du->dropExtensionTable( 'flaggedimages' );

		if ( $dbType == 'mysql' ) {
			$base = __DIR__ . '/mysql';
			// Initial install tables (current schema)
			$du->addExtensionTable( 'flaggedrevs', "$base/FlaggedRevs.sql" );
			$du->addExtensionIndex( 'flaggedrevs', 'fr_user', "$base/patch-fr_user-index.sql" );
			$du->dropExtensionField(
				'flaggedtemplates',
				'ft_title',
				"$base/patch-flaggedtemplates-fr_title.sql"
			);
			$du->dropExtensionField(
				'flaggedrevs',
				'fr_img_name',
				"$base/patch-drop-fr_img.sql"
			);
			$du->modifyExtensionField(
				'flaggedpage_config',
				'fpc_select',
				"$base/patch-nullable-fpc_select.sql"
			);
		} elseif ( $dbType == 'postgres' ) {
			$base = __DIR__ . '/postgres';
			// Initial install tables (current schema)
			$du->addExtensionTable( 'flaggedrevs', "$base/FlaggedRevs.pg.sql" );
			$du->addExtensionIndex( 'flaggedrevs', 'fr_user', "$base/patch-fr_user-index.sql" );
			$du->addExtensionUpdate( [
				'dropFkey',
				'flaggedrevs', 'fr_user'
			] );
			$du->addExtensionUpdate(
				[ 'changePrimaryKey', 'flaggedtemplates', [ 'ft_rev_id', 'ft_tmp_rev_id' ], 'flaggedtemplates_pk' ]
			);
			$du->addExtensionUpdate(
				[ 'dropPgField', 'flaggedtemplates', 'ft_title' ]
			);
			$du->addExtensionUpdate(
				[ 'dropPgField', 'flaggedtemplates', 'ft_namespace' ]
			);
			$du->addExtensionUpdate(
				[ 'dropPgIndex', 'flaggedrevs', 'fr_img_sha1' ]
			);
			$du->addExtensionUpdate(
				[ 'dropPgField', 'flaggedrevs', 'fr_img_name' ]
			);
			$du->addExtensionUpdate(
				[ 'dropPgField', 'flaggedrevs', 'fr_img_timestamp' ]
			);
			$du->addExtensionUpdate(
				[ 'dropPgField', 'flaggedrevs', 'fr_img_sha1' ]
			);
			$du->addExtensionUpdate(
				[ 'changeNullableField', 'flaggedpage_config', 'fpc_select', 'NULL' ]
			);
		} elseif ( $dbType == 'sqlite' ) {
			$base = __DIR__ . '/mysql';
			$du->addExtensionTable( 'flaggedrevs', "$base/FlaggedRevs.sql" );
			$du->dropExtensionField(
				'flaggedtemplates',
				'ft_title',
				__DIR__ . '/sqlite/patch-flaggedtemplates-fr_title.sql'
			);
			$du->dropExtensionField(
				'flaggedrevs',
				'fr_img_name',
				"$base/patch-drop-fr_img.sql"
			);
		}
	}
}
