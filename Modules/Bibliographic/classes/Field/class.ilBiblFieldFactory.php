<?php

/**
 * Class ilBiblFieldFactory
 *
 * @author Fabian Schmid <fs@studer-raimann.ch>
 */
class ilBiblFieldFactory implements ilBiblFieldFactoryInterface {

	/**
	 * @var \ilBiblTypeInterface
	 */
	protected $type;


	/**
	 * ilBiblFieldFactory constructor.
	 *
	 * @param \ilBiblTypeInterface $type
	 */
	public function __construct(\ilBiblTypeInterface $type) { $this->type = $type; }


	/**
	 * @return \ilBiblTypeInterface
	 */
	public function getType() {
		return $this->type;
	}


	/**
	 * @inheritDoc
	 */
	public function findById($id) {
		/**
		 * @var $inst ilBiblField
		 */
		$inst = ilBiblField::findOrFail($id);
		if ($this->type->isStandardField($inst->getIdentifier()) != $inst->getisStandardField()) {
			$inst->setIsStandardField($this->type->isStandardField($inst->getIdentifier()));
			$inst->update();
		}

		return $inst;
	}


	/**
	 * @inheritdoc
	 */
	public function getFieldByTypeAndIdentifier($type, $identifier) {
		$this->checkType($type);
		$inst = $this->getARInstance($type, $identifier);
		if (!$inst) {
			throw new ilException("bibliografic identifier {$identifier} not found");
		}

		return $inst;
	}


	/**
	 * @inheritdoc
	 */
	public function findOrCreateFieldByTypeAndIdentifier($type, $identifier) {
		$this->checkType($type);
		$inst = $this->getARInstance($type, $identifier);
		if (!$inst) {
			$inst = new ilBiblField();
			$inst->create();
		}
		$inst->setDataType($type);
		$inst->setIdentifier($identifier);
		$inst->setIsStandardField($this->getType()->isStandardField($identifier));
		$inst->update();

		return $inst;
	}


	/**
	 * @inheritDoc
	 */
	public function getAvailableFieldsForObjId($obj_id) {
		global $DIC;
		$sql = "SELECT DISTINCT(il_bibl_attribute.name), il_bibl_data.file_type FROM il_bibl_data 
					JOIN il_bibl_entry ON il_bibl_entry.data_id = il_bibl_data.id
					JOIN il_bibl_attribute ON il_bibl_attribute.entry_id = il_bibl_entry.id
				WHERE il_bibl_data.id = %s;";

		$result = $DIC->database()->queryF($sql, [ 'integer' ], [ $obj_id ]);

		$data = [];
		while ($d = $DIC->database()->fetchObject($result)) {
			$data[] = $this->findOrCreateFieldByTypeAndIdentifier($d->file_type, $d->name);
		}

		return $data;
	}




	/**
	 * @inheritDoc
	 */
	public function filterAllFieldsForType(ilBiblTypeInterface $type, ilBiblTableQueryInfoInterface $queryInfo = null) {
		return $this->getCollectionForFilter($type, $queryInfo)->get();
	}


	/**
	 * @inheritDoc
	 */
	public function filterAllFieldsForTypeAsArray(ilBiblTypeInterface $type, ilBiblTableQueryInfoInterface $queryInfo = null) {
		return $this->getCollectionForFilter($type, $queryInfo)->getArray();
	}


	/**
	 * @inheritDoc
	 */
	public function getBiblAttributeById($id) {
		global $DIC;
		$result = $DIC->database()->query("SELECT * FROM il_bibl_attribute WHERE id = "
		                                  . $DIC->database()->quote($id, "integer"));

		$data = [];
		while ($d = $DIC->database()->fetchAssoc($result)) {
			$data[] = $d['name'];
		}

		return $data['name'];
	}


	/**
	 * @inheritDoc
	 */
	public function deleteBiblAttributeById($id) {
		global $DIC;
		$DIC->database()->manipulate("DELETE FROM il_bibl_attribute WHERE id = " . $DIC->database()
		                                                                               ->quote($id, "integer"));
	}


	/**
	 * @inheritDoc
	 */
	public function getAllAttributeNamesByDataType($data_type) {
		global $DIC;

		switch ($data_type) {
			case ilBiblField::DATA_TYPE_RIS:
				$data_type = "ris";
				break;
			case ilBiblField::DATA_TYPE_BIBTEX:
				$data_type = "bib";
				break;
		}

		$sql = "SELECT DISTINCT (il_bibl_attribute.id), (il_bibl_attribute.name), filename FROM il_bibl_attribute
				JOIN il_bibl_entry ON il_bibl_attribute.entry_id = il_bibl_entry.id
				JOIN il_bibl_data ON il_bibl_data.id = il_bibl_entry.data_id";

		$result = $DIC->database()->query($sql);

		$data = [];
		$i = 0;
		while ($d = $DIC->database()->fetchAssoc($result)) {
			$file_parts = pathinfo($d['filename']);
			if ($file_parts['extension'] == $data_type) {
				$data[$i]['id'] = $d['id'];
				$data[$i]['name'] = $d['name'];
				$data[$i]['filename'] = $d['filename'];
				$i ++;
			}
		}

		return $data;
	}


	/**
	 * @inheritDoc
	 */
	public function getAllAttributeNamesByIdentifier($identifier) {
		global $DIC;

		$sql = "SELECT DISTINCT (il_bibl_attribute.id), (il_bibl_attribute.name), filename FROM il_bibl_attribute
				JOIN il_bibl_entry ON il_bibl_attribute.entry_id = il_bibl_entry.id
				JOIN il_bibl_data ON il_bibl_data.id = il_bibl_entry.data_id WHERE "
		       . $DIC->database()->like("il_bibl_attribute.name", "text", "%" . $identifier . "%");

		$result = $DIC->database()->query($sql);

		$data = [];

		while ($d = $DIC->database()->fetchAssoc($result)) {
			$data[] = $d;
		}

		return $data;
	}


	/**
	 * @inheritDoc
	 */
	public function getAttributeNameAndFileName($obj_id) {
		global $DIC;
		$sql = "SELECT DISTINCT(il_bibl_attribute.name), filename FROM il_bibl_attribute
				JOIN il_bibl_entry ON il_bibl_attribute.entry_id = il_bibl_entry.id
				JOIN il_bibl_data ON il_bibl_data.id = il_bibl_entry.data_id";

		$result = $DIC->database()->queryF($sql, [ 'integer' ], [ $obj_id ]);

		$data = [];
		while ($d = $DIC->database()->fetchAssoc($result)) {
			$data[] = $d['name'];
		}

		return $data;
	}


	/**
	 * @inheritDoc
	 */
	public function getilBiblDataById($id) {
		global $DIC;
		$data = array();
		$set = $DIC->database()->query("SELECT * FROM il_bibl_data " . " WHERE id = "
		                               . $DIC->database()->quote($id, "integer"));
		while ($rec = $DIC->database()->fetchAssoc($set)) {
			$data = $rec;
		}

		return $data;
	}


	/**
	 * @inheritDoc
	 */
	public function findOrCreate(ilBiblAttribute $ilBiblAttribute) {
		$ilBiblField = ilBiblField::where([ 'identifier' => $ilBiblAttribute->getName() ])->first();
		if ($ilBiblField === null) {
			$ilBiblField = new ilBiblField();
			$ilBiblField->setIdentifier($ilBiblAttribute->getName());

			$ilBiblEntry = ilBiblEntry::getEntryById($ilBiblAttribute->getEntryId());
			$ilBiblData = $this->getIlBiblDataById($ilBiblEntry['data_id']);

			$file_parts = pathinfo($ilBiblData['filename']);
			$extension = $file_parts['extension'];

			$ilBiblTypeFactory = new ilBiblTypeFactory();
			$data_type = $ilBiblTypeFactory->convertFileEndingToDataType($extension);

			$ilBiblField->setDataType($data_type);
			$type_inst = $ilBiblTypeFactory->getInstanceForType($data_type);
			$ilBiblField->setIsStandardField($type_inst->isStandardField($ilBiblAttribute->getName()));

			$ilBiblField->create();
		}

		return $ilBiblField;
	}

	/**
	 * @inheritDoc
	 */
	public function sortAttributesByFieldPosition($attributes) {
		$il_bibl_fields = [];
		foreach($attributes as $key => $value) {
			$ilBiblField = $this->findByIdentifier($key);
			if(!empty($ilBiblField)) {
				$il_bibl_fields[] = $ilBiblField;
			}
		}
		for($i = 0; $i < $il_bibl_fields; $i++) {
			if($il_bibl_fields[$i] > $il_bibl_fields[$i++]) {
				$temp = $il_bibl_fields[$i];
				$il_bibl_fields[$i] = $il_bibl_fields[$i++];
				$il_bibl_fields[$i++] = $temp;
 			}
		}
		return array_merge($il_bibl_fields, $attributes);
	}


	public function findByIdentifier($identifier) {
		return ilBiblField::where(array('identifier' => $identifier))->first();
	}





	// Internal Methods


	/**
	 * @param int    $type
	 * @param string $identifier
	 *
	 * @return \ilBiblField
	 */
	private function getARInstance($type, $identifier) {
		return ilBiblField::where([ "identifier" => $identifier, "data_type" => $type ])->first();
	}


	/**
	 * @param $type
	 *
	 * @throws \ilException
	 */
	private function checkType($type) {
		switch ($type) {
			case ilBiblTypeFactoryInterface::DATA_TYPE_BIBTEX:
			case ilBiblTypeFactoryInterface::DATA_TYPE_RIS:
				break;
			default:
				throw new ilException("bibliografic type not found");
		}
	}


	/**
	 * @param \ilBiblTypeInterface           $type
	 * @param \ilBiblTableQueryInfoInterface $queryInfo
	 *
	 * @return \ActiveRecordList
	 */
	private function getCollectionForFilter(ilBiblTypeInterface $type, ilBiblTableQueryInfoInterface $queryInfo = null) {
		$collection = ilBiblField::getCollection();

		$collection->where(array( 'data_type' => $type->getId() ));

		if ($queryInfo) {
			$sorting_column = $queryInfo->getSortingColumn() ? $queryInfo->getSortingColumn() : null;
			$offset = $queryInfo->getOffset() ? $queryInfo->getOffset() : 0;
			$sorting_direction = $queryInfo->getSortingDirection();
			$limit = $queryInfo->getLimit();
			if ($sorting_column) {
				$collection->orderBy($sorting_column, $sorting_direction);
			}
			$collection->limit($offset, $limit);

			foreach ($queryInfo->getFilters() as $queryFilter) {
				switch ($queryFilter->getFieldName()) {
					default:
						$collection->where(array( $queryFilter->getFieldName() => $queryFilter->getFieldValue() ), $queryFilter->getOperator());
						break;
				}
			}
		}

		return $collection;
	}
}

