<?php

/**
 * PresenterSubmitStep3Form.inc.php
 *
 * Copyright (c) 2003-2007 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @package presenter.form.submit
 *
 * Form for Step 3 of presenter paper submission.
 *
 * $Id$
 */

import("presenter.form.submit.PresenterSubmitForm");

class PresenterSubmitStep3Form extends PresenterSubmitForm {
	
	/**
	 * Constructor.
	 */
	function PresenterSubmitStep3Form($paper) {
		parent::PresenterSubmitForm($paper, 3);

		// Validation checks for this form
	}
	
	/**
	 * Initialize form data from current paper.
	 */
	function initData() {
		if (isset($this->paper)) {
			$paper = &$this->paper;
			$this->_data = array(
			);
		}
	}
	
	/**
	 * Assign form data to user-submitted data.
	 */
	function readInputData() {
		$this->readUserVars(
			array(
			)
		);
	}
	
	/**
	 * Display the form.
	 */
	function display() {
		$templateMgr = &TemplateManager::getManager();
		
		// Get supplementary files for this paper
		$paperFileDao = &DAORegistry::getDAO('PaperFileDAO');
		if ($this->paper->getSubmissionFileId() != null) {
			$templateMgr->assign_by_ref('submissionFile', $paperFileDao->getPaperFile($this->paper->getSubmissionFileId()));
		}
		parent::display();
	}
	
	/**
	 * Upload the submission file.
	 * @param $fileName string
	 * @return boolean
	 */
	function uploadSubmissionFile($fileName) {
		import("file.PaperFileManager");

		$paperFileManager = &new PaperFileManager($this->paperId);
		$paperDao = &DAORegistry::getDAO('PaperDAO');

		if ($paperFileManager->uploadedFileExists($fileName)) {
			// upload new submission file, overwriting previous if necessary
			$submissionFileId = $paperFileManager->uploadSubmissionFile($fileName, $this->paper->getSubmissionFileId(), true);
		}

		if (isset($submissionFileId)) {
			$this->paper->setSubmissionFileId($submissionFileId);
			return $paperDao->updatePaper($this->paper);
			
		} else {
			return false;
		}
	}
	
	/**
	 * Save changes to paper.
	 * @return int the paper ID
	 */
	function execute() {
		// Update paper
		$paperDao = &DAORegistry::getDAO('PaperDAO');
		$paper = &$this->paper;
		
		if ($paper->getSubmissionProgress() <= $this->step) {
			$paper->stampStatusModified();
			$paper->setSubmissionProgress($this->step + 1);
			$paperDao->updatePaper($paper);
		}
		
		return $this->paperId;
	}
	
}

?>