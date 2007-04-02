<?php

/**
 * ConferenceHistoryHandler.inc.php
 *
 * Copyright (c) 2003-2007 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @package pages.director
 *
 * Handle requests for conference event log funcs.
 *
 * $Id$
 */

class ConferenceHistoryHandler extends ManagerHandler {
	/**
	 * View conference event log.
	 */
	function conferenceEventLog($args) {
		$logId = isset($args[0]) ? (int) $args[0] : 0;
		parent::validate();

		$conference =& Request::getConference();

		parent::setupTemplate();

		$templateMgr = &TemplateManager::getManager();

		$templateMgr->assign_by_ref('conference', $conference);

		if ($logId) {
			$logDao = &DAORegistry::getDAO('ConferenceEventLogDAO');
			$logEntry = &$logDao->getLogEntry($logId);
			if ($logEntry && $logEntry->getConferenceId() != $conference->getConferenceId()) Request::redirect(null, null, null, 'index');
		}

		if (isset($logEntry)) {
			$templateMgr->assign('logEntry', $logEntry);
			$templateMgr->display('manager/conferenceEventLogEntry.tpl');

		} else {
			$rangeInfo = &Handler::getRangeInfo('eventLogEntries');

			import('conference.log.ConferenceLog');
			$eventLogEntries = &ConferenceLog::getEventLogEntries($conference->getConferenceId(), null, $rangeInfo);
			$templateMgr->assign('eventLogEntries', $eventLogEntries);
			$templateMgr->display('manager/conferenceEventLog.tpl');
		}
	}

	/**
	 * View conference event log by record type.
	 */
	function conferenceEventLogType($args) {
		$assocType = isset($args[1]) ? (int) $args[0] : null;
		$assocId = isset($args[2]) ? (int) $args[1] : null;
		parent::validate();
		parent::setupTemplate();

		$conference =& Request::getConference();

		$rangeInfo = &Handler::getRangeInfo('eventLogEntries');
		$logDao = &DAORegistry::getDAO('ConferenceEventLogDAO');
		$eventLogEntries = &$logDao->getConferenceLogEntriesByAssoc($conference->getConferenceId(), null, $assocType, $assocId, $rangeInfo);

		$templateMgr = &TemplateManager::getManager();

		$templateMgr->assign('showBackLink', true);
		$templateMgr->assign('isDirector', Validation::isDirector());
		$templateMgr->assign_by_ref('conference', $conference);
		$templateMgr->assign_by_ref('eventLogEntries', $eventLogEntries);
		$templateMgr->display('trackDirector/conferenceEventLog.tpl');
	}

	/**
	 * Clear conference event log entries.
	 */
	function clearConferenceEventLog($args) {
		$logId = isset($args[0]) ? (int) $args[0] : 0;
		parent::validate();
		$conference =& Request::getConference();

		$logDao = &DAORegistry::getDAO('ConferenceEventLogDAO');

		if ($logId) {
			$logDao->deleteLogEntry($logId, $conference->getConferenceId());
		} else {
			$logDao->deleteConferenceLogEntries($conference->getConferenceId());
		}

		Request::redirect(null, null, null, 'conferenceEventLog');
	}
}

?>