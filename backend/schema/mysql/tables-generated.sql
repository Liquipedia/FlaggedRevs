-- This file is automatically generated using maintenance/generateSchemaSql.php.
-- Source: backend/schema/tables.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
CREATE TABLE /*_*/flaggedpages (
  fp_page_id INT UNSIGNED NOT NULL,
  fp_reviewed TINYINT(1) DEFAULT 0 NOT NULL,
  fp_pending_since BINARY(14) DEFAULT NULL,
  fp_stable INT UNSIGNED NOT NULL,
  fp_quality TINYINT(1) DEFAULT NULL,
  INDEX fp_reviewed_page (fp_reviewed, fp_page_id),
  INDEX fp_quality_page (fp_quality, fp_page_id),
  INDEX fp_pending_since (fp_pending_since),
  PRIMARY KEY(fp_page_id)
) /*$wgDBTableOptions*/;


CREATE TABLE /*_*/flaggedpage_pending (
  fpp_page_id INT UNSIGNED NOT NULL,
  fpp_quality TINYINT(1) NOT NULL,
  fpp_rev_id INT UNSIGNED NOT NULL,
  fpp_pending_since BINARY(14) NOT NULL,
  INDEX fpp_quality_pending (fpp_quality, fpp_pending_since),
  PRIMARY KEY(fpp_page_id, fpp_quality)
) /*$wgDBTableOptions*/;


CREATE TABLE /*_*/flaggedrevs (
  fr_rev_id INT UNSIGNED NOT NULL,
  fr_rev_timestamp BINARY(14) NOT NULL,
  fr_page_id INT UNSIGNED NOT NULL,
  fr_user INT UNSIGNED NOT NULL,
  fr_timestamp BINARY(14) NOT NULL,
  fr_quality TINYINT(1) DEFAULT 0 NOT NULL,
  fr_tags MEDIUMBLOB NOT NULL,
  fr_flags TINYBLOB NOT NULL,
  INDEX fr_page_rev (fr_page_id, fr_rev_id),
  INDEX fr_page_time (fr_page_id, fr_rev_timestamp),
  INDEX fr_page_qal_rev (
    fr_page_id, fr_quality, fr_rev_id
  ),
  INDEX fr_page_qal_time (
    fr_page_id, fr_quality, fr_rev_timestamp
  ),
  INDEX fr_user (fr_user),
  PRIMARY KEY(fr_rev_id)
) /*$wgDBTableOptions*/;


CREATE TABLE /*_*/flaggedtemplates (
  ft_rev_id INT UNSIGNED NOT NULL,
  ft_tmp_rev_id INT UNSIGNED NOT NULL,
  PRIMARY KEY(ft_rev_id, ft_tmp_rev_id)
) /*$wgDBTableOptions*/;


CREATE TABLE /*_*/flaggedpage_config (
  fpc_page_id INT UNSIGNED NOT NULL,
  fpc_override TINYINT(1) NOT NULL,
  fpc_level VARBINARY(60) DEFAULT NULL,
  fpc_expiry VARBINARY(14) NOT NULL,
  INDEX fpc_expiry (fpc_expiry),
  PRIMARY KEY(fpc_page_id)
) /*$wgDBTableOptions*/;


CREATE TABLE /*_*/flaggedrevs_tracking (
  ftr_from INT UNSIGNED DEFAULT 0 NOT NULL,
  ftr_namespace INT DEFAULT 0 NOT NULL,
  ftr_title VARBINARY(255) DEFAULT '' NOT NULL,
  INDEX frt_namespace_title_from (
    ftr_namespace, ftr_title, ftr_from
  ),
  PRIMARY KEY(
    ftr_from, ftr_namespace, ftr_title
  )
) /*$wgDBTableOptions*/;


CREATE TABLE /*_*/flaggedrevs_promote (
  frp_user_id INT UNSIGNED NOT NULL,
  frp_user_params MEDIUMBLOB NOT NULL,
  PRIMARY KEY(frp_user_id)
) /*$wgDBTableOptions*/;


CREATE TABLE /*_*/flaggedrevs_statistics (
  frs_timestamp BINARY(14) NOT NULL,
  frs_stat_key VARCHAR(255) NOT NULL,
  frs_stat_val BIGINT NOT NULL,
  INDEX frs_timestamp (frs_timestamp),
  PRIMARY KEY(frs_stat_key, frs_timestamp)
) /*$wgDBTableOptions*/;
