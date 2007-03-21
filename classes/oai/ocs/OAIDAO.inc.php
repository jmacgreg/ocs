<?php

/**
 * OAIDAO.inc.php
 *
 * Copyright (c) 2003-2007 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @package oai.ocs
 *
 * DAO operations for the OJS OAI interface.
 *
 * $Id$
 */

import('oai.OAI');

class OAIDAO extends DAO {
 
 	/** @var $oai ConferenceOAI parent OAI object */
 	var $oai;
 	
 	/** Helper DAOs */
 	var $conferenceDao;
 	var $trackDao;
 	var $presenterDao;
 	var $suppFileDao;
 	var $conferenceSettingsDao;
 	
 
 	/**
	 * Constructor.
	 */
	function OAIDAO() {
		parent::DAO();
		$this->conferenceDao = &DAORegistry::getDAO('ConferenceDAO');
		$this->trackDao = &DAORegistry::getDAO('TrackDAO');
		$this->presenterDao = &DAORegistry::getDAO('PresenterDAO');
		$this->suppFileDao = &DAORegistry::getDAO('SuppFileDAO');
		$this->conferenceSettingsDao = &DAORegistry::getDAO('ConferenceSettingsDAO');
	}
	
	/**
	 * Set parent OAI object.
	 * @param ConferenceOAI
	 */
	function setOAI(&$oai) {
		$this->oai = $oai;
	}
	
	
	//
	// Records
	//
	
	/**
	 * Return the *nix timestamp of the earliest published paper.
	 * @param $conferenceId int optional
	 * @return int
	 */
	function getEarliestDatestamp($conferenceId = null) {
		$result = &$this->retrieve(
			'SELECT MIN(pp.date_published)
			FROM published_papers pp'
			. (isset($conferenceId) ? ' WHERE i.conference_id = ?' : ''),
			
			isset($conferenceId) ? $conferenceId : false
		);
		
		if (isset($result->fields[0])) {
			$timestamp = strtotime($this->datetimeFromDB($result->fields[0]));
		}
		if (!isset($timestamp) || $timestamp == -1) {
			$timestamp = 0;
		}

		$result->Close();
		unset($result);

		return $timestamp;
	}
	
	/**
	 * Check if an paper ID specifies a published paper.
	 * @param $paperId int
	 * @param $conferenceId int optional
	 * @return boolean
	 */
	function recordExists($paperId, $conferenceId = null) {
		$result = &$this->retrieve(
			'SELECT COUNT(*)
			FROM published_papers pp'
			. (isset($conferenceId) ? ', sched_confs s' : '')
			. ' WHERE pp.paper_id = ?'
			. (isset($conferenceId) ? ' AND s.conference_id = ? AND pp.sched_conf_id = s.sched_conf_id' : ''),
			
			isset($conferenceId) ? array($paperId, $conferenceId) : $paperId
		);
		
		$returner = $result->fields[0] == 1;

		$result->Close();
		unset($result);

		return $returner;
	}
	
	/**
	 * Return OAI record for specified paper.
	 * @param $paperId int
	 * @param $conferenceId int optional
	 * @return OAIRecord
	 */
	function &getRecord($paperId, $conferenceId = null) {
		$result = &$this->retrieve(
			'SELECT pp.*, p.*,
			c.path AS conference_path,
			c.title AS conference_title,
			c.conference_id AS conference_id,
			s.path AS sched_conf_path,
			s.title AS sched_conf_title,
			t.abbrev AS track_abbrev,
			t.identify_type AS track_item_type,
			pp.date_published AS date_published,
			FROM published_papers pp, conferences c, sched_confs s, papers p
			LEFT JOIN tracks t ON t.track_id = p.track_id
			WHERE pp.paper_id = p.paper_id AND c.conference_id = s.conference_id AND s.sched_conf_id = p.sched_conf_id
			AND pp.paper_id = ?'
			. (isset($conferenceId) ? ' AND c.conference_id = ?' : ''),
			isset($conferenceId) ? array($paperId, $conferenceId) : $paperId
		);

		$returner = null;
		if ($result->RecordCount() != 0) {
			$row = &$result->GetRowAssoc(false);
			$returner = &$this->_returnRecordFromRow($row);
		}

		$result->Close();
		unset($result);

		return $returner;
	}
	
	/**
	 * Return set of OAI records matching specified parameters.
	 * @param $conferenceId int
	 * @param $trackId int
	 * @parma $from int timestamp
	 * @parma $until int timestamp
	 * @param $offset int
	 * @param $limit int
	 * @param $total int
	 * @return array OAIRecord
	 */
	function &getRecords($conferenceId, $trackId, $from, $until, $offset, $limit, &$total) {
		$records = array();
		
		$params = array();
		if (isset($conferenceId)) {
			array_push($params, $conferenceId);
		}
		if (isset($trackId)) {
			array_push($params, $trackId);
		}
		$result = &$this->retrieve(
			'SELECT pp.*, p.*,
			c.path AS conference_path,
			c.title AS conference_title,
			c.conference_id AS conference_id,
			s.path AS sched_conf_path,
			s.title AS sched_conf_title,
			t.abbrev AS track_abbrev,
			t.identify_type AS track_item_type
			FROM published_papers pp, conferences c, sched_confs s, papers p
			LEFT JOIN tracks t ON t.track_id = p.track_id
			WHERE pp.paper_id = p.paper_id AND p.sched_conf_id = s.sched_conf_id and s.conference_id = c.conference_id'
			. (isset($conferenceId) ? ' AND c.conference_id = ?' : '')
			. (isset($trackId) ? ' AND p.track_id = ?' : '')
			. (isset($from) ? ' AND pp.date_published >= ' . $this->datetimeToDB($from) : '')
			. (isset($until) ? ' AND pp.date_published <= ' . $this->datetimeToDB($until) : ''),
			$params
		);
		
		$total = $result->RecordCount();
		
		$result->Move($offset);
		for ($count = 0; $count < $limit && !$result->EOF; $count++) {
			$row = &$result->GetRowAssoc(false);
			$records[] = &$this->_returnRecordFromRow($row);
			$result->moveNext();
		}

		$result->Close();
		unset($result);
		
		return $records;
	}
	
	/**
	 * Return set of OAI identifiers matching specified parameters.
	 * @param $conferenceId int
	 * @param $trackId int
	 * @parma $from int timestamp
	 * @parma $until int timestamp
	 * @param $offset int
	 * @param $limit int
	 * @param $total int
	 * @return array OAIIdentifier
	 */
	function &getIdentifiers($conferenceId, $trackId, $from, $until, $offset, $limit, &$total) {
		$records = array();
		
		$params = array();
		if (isset($conferenceId)) {
			array_push($params, $conferenceId);
		}
		if (isset($trackId)) {
			array_push($params, $trackId);
		}
		$result = &$this->retrieve(
			'SELECT pp.paper_id, pp.date_published,
			c.title AS conference_title, c.path AS conference_path,
			s.path AS sched_conf_path,
			t.abbrev as track_abbrev
			FROM published_papers pp, conferences c, sched_confs s, papers p
			LEFT JOIN tracks t ON t.track_id = p.track_id
			WHERE pp.paper_id = p.paper_id AND p.sched_conf_id = s.sched_conf_id AND s.conference_id = c.conference_id'
			. (isset($conferenceId) ? ' AND c.conference_id = ?' : '')
			. (isset($trackId) ? ' AND p.track_id = ?' : '')
			. (isset($from) ? ' AND pp.date_published >= ' . $this->datetimeToDB($from) : '')
			. (isset($until) ? ' AND pp.date_published <= ' . $this->datetimeToDB($until) : ''),
			$params
		);
		
		$total = $result->RecordCount();
		
		$result->Move($offset);
		for ($count = 0; $count < $limit && !$result->EOF; $count++) {
			$row = &$result->GetRowAssoc(false);
			$records[] = &$this->_returnIdentifierFromRow($row);
			$result->moveNext();
		}

		$result->Close();
		unset($result);
		
		return $records;
	}
	
	/**
	 * Return OAIRecord object from database row.
	 * @param $row array
	 * @return OAIRecord
	 */
	function &_returnRecordFromRow(&$row) {
		$record = &new OAIRecord();
		
		$paperId = $row['paper_id'];
		if ($this->conferenceSettingsDao->getSetting($row['conference_id'], 'enablePublicPaperId')) {
			if (!empty($row['public_paper_id'])) {
				$paperId = $row['public_paper_id'];
			}
		}
		
		// FIXME Use public ID in OAI identifier?
		// FIXME Use "last-modified" field for datestamp?
		$record->identifier = $this->oai->paperIdToIdentifier($row['paper_id']);
		$record->datestamp = $this->oai->UTCDate(strtotime($this->datetimeFromDB($row['date_published'])));
		$record->sets = array($row['conference_path'] . ':' . $row['track_abbrev']);
		
		$record->url = Request::url($row['conference_path'], $row['sched_conf_path'], 'paper', 'view', array($paperId));
		$record->title = strip_tags($row['title']); // FIXME include localized titles as well?
		$record->creator = array();
		$record->subject = array($row['discipline'], $row['subject'], $row['subject_class']);
		$record->description = strip_tags($row['abstract']);
		$record->publisher = $row['conference_title'];
		$record->contributor = array($row['sponsor']);
		$record->date = date('Y-m-d', strtotime($this->datetimeFromDB($row['date_published'])));
		$record->type = array(empty($row['track_item_type']) ? Locale::translate('rt.metadata.pkp.peerReviewed') : $row['track_item_type'], $row['type']);
		$record->format = array();
		$record->source = $row['conference_title'] . '; ' . $row['sched_conf_title'];
		$record->language = $row['language'];
		$record->relation = array();
		$record->coverage = array($row['coverage_geo'], $row['coverage_chron'], $row['coverage_sample']);
		$record->rights = $this->conferenceSettingsDao->getSetting($row['conference_id'], 'copyrightNotice');
		$record->pages = $row['pages'];
		
		// Get publisher
		$publisher = $this->conferenceSettingsDao->getSetting($row['conference_id'], 'publisher');
		if (isset($publisher['institution']) && !empty($publisher['institution'])) {
			$record->publisher = $publisher['institution'];
		}
		
		// Get presenter names
		$presenters = $this->presenterDao->getPresentersByPaper($row['paper_id']);
		for ($i = 0, $num = count($presenters); $i < $num; $i++) {
			$presenterName = $presenters[$i]->getFullName();
			$affiliation = $presenters[$i]->getAffiliation();
			if (!empty($affiliation)) {
				$presenterName .= '; ' . $affiliation;
			}
			$record->creator[] = $presenterName;
		}
		
		// Get galley formats
		$result = &$this->retrieve(
			'SELECT DISTINCT(f.file_type) FROM paper_galleys g, paper_files f WHERE g.file_id = f.file_id AND g.paper_id = ?',
			$row['paper_id']
		);
		while (!$result->EOF) {
			$record->format[] = $result->fields[0];
			$result->MoveNext();
		}

		$result->Close();
		unset($result);
		
		// Get supplementary files
		$suppFiles =& $this->suppFileDao->getSuppFilesByPaper($row['paper_id']);
		for ($i = 0, $num = count($suppFiles); $i < $num; $i++) {
			// FIXME replace with correct URL
			$record->relation[] = Request::url($row['conference_path'], $row['sched_conf_path'], 'paper', 'download', array($paperId, $suppFiles[$i]->getFileId()));
		}
		
		return $record;
	}
	
	/**
	 * Return OAIIdentifier object from database row.
	 * @param $row array
	 * @return OAIIdentifier
	 */
	function &_returnIdentifierFromRow(&$row) {
		$record = &new OAIRecord();
		
		$record->identifier = $this->oai->paperIdToIdentifier($row['paper_id']);
		$record->datestamp = $this->oai->UTCDate(strtotime($this->datetimeFromDB($row['date_published'])));
		$record->sets = array($row['conference_path'] . ':' . $row['track_abbrev']);
		
		return $record;
	}
	
	
	//
	// Resumption tokens
	//
	
	/**
	 * Clear stale resumption tokens.
	 */
	function clearTokens() {
		$this->update(
			'DELETE FROM oai_resumption_tokens WHERE expire < ?', time()
		);
	}
	
	/**
	 * Retrieve a resumption token.
	 * @return OAIResumptionToken
	 */
	function &getToken($tokenId) {
		$result = &$this->retrieve(
			'SELECT * FROM oai_resumption_tokens WHERE token = ?', $tokenId
		);
		
		if ($result->RecordCount() == 0) {
			$token = null;
			
		} else {
			$row = &$result->getRowAssoc(false);
			$token = &new OAIResumptionToken($row['token'], $row['record_offset'], unserialize($row['params']), $row['expire']);
		}

		$result->Close();
		unset($result);

		return $token;
	}
	
	/**
	 * Insert an OAI resumption token, generating a new ID.
	 * @param $token OAIResumptionToken
	 * @return OAIResumptionToken
	 */
	function &insertToken(&$token) {
		do {
			// Generate unique token ID
			$token->id = md5(uniqid(mt_rand(), true));
			$result = &$this->retrieve(
				'SELECT COUNT(*) FROM oai_resumption_tokens WHERE token = ?',
				$token->id
			);
			$val = $result->fields[0];

			$result->Close();
			unset($result);
		} while($val != 0);
		
		$this->update(
			'INSERT INTO oai_resumption_tokens (token, record_offset, params, expire)
			VALUES
			(?, ?, ?, ?)',
			array($token->id, $token->offset, serialize($token->params), $token->expire)
		);
		
		return $token;
	}
	
	
	//
	// Sets
	//
	
	/**
	 * Return hierarchy of OAI sets (conferences plus conference tracks).
	 * @param $conferenceId int
	 * @param $offset int
	 * @param $total int
	 * @return array OAISet
	 */
	function &getConferenceSets($conferenceId, $offset, &$total) {
		if (isset($conferenceId)) {
			$conferences = array($this->conferenceDao->getConference($conferenceId));
		} else {
			$conferences = &$this->conferenceDao->getConferences();
			$conferences = &$conferences->toArray();
		}
		
		// FIXME Set descriptions
		$sets = array();
		foreach ($conferences as $conference) {
			$title = $conference->getTitle();
			$abbrev = $conference->getPath();
			array_push($sets, new OAISet($abbrev, $title, ''));
			
			$tracks = &$this->trackDao->getConferenceTracks($conference->getConferenceId());
			foreach ($tracks->toArray() as $track) {
				array_push($sets, new OAISet($abbrev . ':' . $track->getAbbrev(), $track->getTitle(), ''));
			}
		}
		
		if ($offset != 0) {
			$sets = array_slice($sets, $offset);
		}
		
		return $sets;
	}
	
	/**
	 * Return the conference ID and track ID corresponding to a conference/track pairing.
	 * @param $conferenceSpec string
	 * @param $trackSpec string
	 * @param $restrictConferenceId int
	 * @return array (int, int)
	 */
	function getSetConferenceTrackId($conferenceSpec, $trackSpec, $restrictConferenceId = null) {
		$conferenceId = null;
		
		$conference = &$this->conferenceDao->getConferenceByPath($conferenceSpec);
		if (!isset($conference) || (isset($restrictConferenceId) && $conference->getConferenceId() != $restrictConferenceId)) {
			return array(0, 0);
		}
		
		$conferenceId = $conference->getConferenceId();
		$trackId = null;
		
		if (isset($trackSpec)) {
			$track = &$this->trackDao->getTrackByAbbrev($trackSpec, $conference->getConferenceId());
			if (isset($track)) {
				$trackId = $track->getTrackId();
			} else {
				$trackId = 0;
			}
		}
		
		return array($conferenceId, $trackId);
	}
	
}

?>