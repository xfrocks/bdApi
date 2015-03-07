<?php

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit();
}

function xfac_sync_mysqlDateToGmtTimestamp($string)
{
    if (preg_match('/^(\d+)-(\d+)-(\d+) (\d+):(\d+):(\d+)$/', trim($string), $m)) {
        return gmmktime($m[4], $m[5], $m[6], $m[2], $m[3], $m[1]);
    }

    return 0;
}

function xfac_sync_updateRecord($provider, $cType, $cId, $syncId, $syncDate = 0, $syncData = array())
{
    global $wpdb;

    if ($syncDate === 0) {
        $syncDate = time();
    }

    return $wpdb->query($wpdb->prepare("
		REPLACE INTO {$wpdb->prefix}xfac_sync
		SET provider = %s,
			provider_content_type = %s,
			provider_content_id = %s,
			sync_id = %d,
			sync_date = %d,
			sync_data = %s
	", $provider, $cType, $cId, $syncId, $syncDate, serialize($syncData)));
}

function xfac_sync_deleteRecord($record)
{
    global $wpdb;

    return $wpdb->query($wpdb->prepare("
		DELETE FROM {$wpdb->prefix}xfac_sync
		WHERE provider = %s
			AND provider_content_type = %s
			AND provider_content_id = %s
			AND sync_id = %d
	", $record->provider, $record->provider_content_type, $record->provider_content_id, $record->sync_id));
}

function xfac_sync_updateRecordDate($record, $syncDate = 0)
{
    global $wpdb;

    if ($syncDate === 0) {
        $syncDate = time();
    }

    return $wpdb->query($wpdb->prepare("
		UPDATE {$wpdb->prefix}xfac_sync
		SET sync_date = %d
		WHERE provider = %s
			AND provider_content_type = %s
			AND provider_content_id = %s
			AND sync_id = %d
	", $syncDate, $record->provider, $record->provider_content_type, $record->provider_content_id, $record->sync_id));
}

function xfac_sync_getRecordsByProviderTypeAndSyncId($provider, $cType, $syncId)
{
    global $wpdb;

    $records = $wpdb->get_results($wpdb->prepare("
		SELECT *
		FROM {$wpdb->prefix}xfac_sync
		WHERE provider = %s
			AND provider_content_type = %s
			AND sync_id = %d
	", $provider, $cType, $syncId));

    _xfac_sync_prepareRecords($records);

    return $records;
}

function xfac_sync_getRecordsByProviderTypeAndIds($provider, $cType, array $cIds)
{
    global $wpdb;

    $records = $wpdb->get_results($wpdb->prepare("
		SELECT *
		FROM {$wpdb->prefix}xfac_sync
		WHERE provider = %s
			AND provider_content_type = %s
			AND provider_content_id IN (" . implode(',', array_map('intval', $cIds)) . ")
	", $provider, $cType));

    _xfac_sync_prepareRecords($records);

    return $records;
}

function xfac_sync_getRecordsByProviderTypeAndRecent($provider, $cType, $recentThreshold = 604800)
{
    // 604800 equals 7 days, we only sync data that was updated within a week

    global $wpdb;

    $records = $wpdb->get_results($wpdb->prepare("
		SELECT *
		FROM {$wpdb->prefix}xfac_sync
		WHERE provider = %s
			AND provider_content_type = %s
			AND sync_date > %d
	", $provider, $cType, time() - $recentThreshold));

    _xfac_sync_prepareRecords($records);

    return $records;
}

function _xfac_sync_prepareRecords(&$records)
{
    foreach ($records as &$record) {
        $record->syncData = unserialize($record->sync_data);
    }
}
