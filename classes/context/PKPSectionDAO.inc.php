<?php

/**
 * @file classes/context/PKPSectionDAO.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPSectionDAO
 * @ingroup context
 * @see PKPSection
 *
 * @brief Operations for retrieving and modifying Section objects.
 */

abstract class PKPSectionDAO extends DAO {

	/**
	 * Create a new data object.
	 * @return PKPSection
	 */
	abstract function newDataObject();

	/**
	 * Retrieve a section by ID.
	 * @param $sectionId int
	 * @param $journalId int optional
	 * @param $useCache boolean optional
	 * @return Section
	 */
	abstract function getById($sectionId, $contextId = null);

	/**
	 * Generate a new PKPSection object from row.
	 * @param $row array
	 * @return PKPSection
	 */
	function _fromRow($row) {
		$section = $this->newDataObject();

		$section->setReviewFormId($row['review_form_id']);
		$section->setEditorRestricted($row['editor_restricted']);
		$section->setSequence($row['seq']);

		return $section;
	}

	/**
	 * Get the list of fields for which data can be localized.
	 * @return array
	 */
	function getLocaleFieldNames() {
		return array_merge(parent::getLocaleFieldNames(), array('title', 'policy'));
	}

	/**
	 * Delete a section.
	 * @param $section Section
	 */
	function deleteObject($section) {
		return $this->deleteById($section->getId(), $section->getContextId());
	}

	/**
	 * Delete a section by ID.
	 * @param $sectionId int
	 * @param $journalId int optional
	 */
	abstract function deleteById($sectionId, $contextId = null);

	/**
	 * Delete sections by context ID
	 * NOTE: This does not necessarily delete dependent entries.
	 * @param $contextId int
	 */
	function deleteByContextId($contextId) {
		$sections = $this->getByContextId($contextId);
		while ($section = $sections->next()) {
			$this->deleteObject($section);
		}
	}

	/**
	 * Retrieve all sections for a context.
	 * @param $contextId int
	 * @param $rangeInfo DBResultRange optional
	 * @param $submittableOnly boolean ooptional. Whether to return only sections
	 *  that can be submitted to by anyone.
	 * @return DAOResultFactory containing Sections ordered by sequence
	 */
	public function getByContextId($contextId, $rangeInfo = null, $submittableOnly = false) {
		$result = $this->retrieveRange(
			'SELECT * FROM ' . $this->_getTableName() . ' WHERE ' .
				$this->_getContextIdColumnName() . ' = ? ' .
				($submittableOnly ? ' AND editor_restricted = 0' : '') .
				' ORDER BY seq',
			(int) $contextId, $rangeInfo
		);

		return new DAOResultFactory($result, $this, '_fromRow');
	}

	/**
	 * Retrieve the IDs and titles of the sections for a context in an associative array.
	 * @param $contextId int context ID
	 * @param $submittableOnly boolean optional
	 * @return array
	 */
	function getTitles($contextId, $submittableOnly = false) {
		$sections = array();
		$sectionsIterator = $this->getByContextId($contextId);
		while ($section = $sectionsIterator->next()) {
			if ($submittableOnly && $section->getEditorRestricted()) continue;
			$sections[$section->getId()] = $section->getLocalizedTitle();
		}
		return $sections;
	}
}

?>
