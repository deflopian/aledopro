<?php
# powered by Sergey Kozyakov, 16.09.2015
namespace Application\Service;

use Contacts\Model\AledoContact;

class GoogleContactsService {
	public static function parseCSV($sl, $file, $mime) {
		if ($mime != 'text/csv' && $mime != 'application/vnd.ms-excel')	return;
		
		if (file_exists($file)) {
			$handle = fopen($file, 'r');
			$arr = array();
			$row = 0;
			
			while (($data = fgetcsv($handle)) !== false) {
				$row++;
				
				if ($row == 1) continue;
				if ($data[0] == null) continue;
				
				if ($data[28]) {
					$arr[] = str_replace(array("\0", "\r"), array('', ''), $data[28]);
				}
			}
			
			if ($arr) {
				self::fillDB($sl, $arr);
			}
		}
	}
	
	protected static function fillDB($sl, $arr) {
		$table = $sl->get('AledoContactsTable');
		$table->truncate();
		
		foreach ($arr as $item) {
			$contact = new AledoContact();
			$contact->email = $item;
			
			$table->save($contact);
		}
	}
	
	public static function getMails($sl) {
		$arr = array();
		$table = $sl->get('AledoContactsTable');
		
		$items = $table->fetchAll('id ASC');
		foreach ($items as $item) {
			$arr[] = $item->email;
		}
		return implode(',', $arr);
	}
}