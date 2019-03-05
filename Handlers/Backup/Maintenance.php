<?php
/**
 * Copyright Sangoma Technologies, Inc 2017
 */
namespace FreePBX\modules\Backup\Handlers\Backup;

use Carbon\Carbon;
use FreePBX\modules\Backup\Handlers as Handler;

class Maintenance extends \FreePBX\modules\Backup\Handlers\CommonBase {
	private $dryrun = true;
	private $backupInfo;
	private $remoteStorage;
	private $name;
	private $spooldir;
	private $serverName;
	private $localPath;
	private $remotePath;

	public function __construct($freepbx, $id, $transactionId, $pid) {
		parent::__construct($freepbx, $transactionId, $pid);
		$this->id = $id;
		$this->backupInfo = $this->freepbx->Backup->getBackup($this->id);
		$this->remoteStorage = $this->freepbx->Backup->getStorageById($this->id);
		$this->name = str_replace(' ', '_', $this->backupInfo['backup_name']);
		$this->spooldir = $this->freepbx->Config->get("ASTSPOOLDIR");
		$this->serverName = str_replace(' ', '_',$this->freepbx->Config->get('FREEPBX_SYSTEM_IDENT'));
		$this->localPath = sprintf('%s/backup/%s',$this->spooldir,$this->name);
		$this->remotePath =  sprintf('/%s/%s',$this->serverName,$this->name);
	}

	public function setDryRun($mode){
		$this->dryrun = $mode;
	}

	public function processLocal(){
		$files = new \GlobIterator($this->localPath.'/*.tar.gz*');
		$maintfiles = [];
		foreach ($files as $file) {
			$parsed = $this->parseFile($file->getBasename());
			if($parsed === false){
				continue;
			}
			$backupDate = Carbon::createFromTimestamp($parsed['timestamp'], 'UTC');
			if(isset($this->backupInfo['maintage']) && $this->backupInfo['maintage'] > 1){
				if($backupDate->diffInDays() > $backupInfo['maintage']){
					if($this->dryrun){
					 $this->log(sprintf("\t"."UNLINK %s/%s",$file->getPath(),$file->getBasename().'.tar.gz'),'DEBUG');
						continue;
					}
					$this->fs->remove($file->getPath().'/'.$file->getBasename());
					continue;
				}
			}
			if($this->dryrun){
				$this->log("\t".sprintf("Adding %s/%s to maintfiles with a key of %s",$file->getPath(),$file->getBasename(),$parsed['timestamp']),'DEBUG');
			}
			if(!$parsed['isCheckSum']){
				$maintfiles[$parsed['timestamp']] = $file->getPath().'/'.$file->getBasename();
			}
		}
		asort($maintfiles,SORT_NUMERIC);
		if(isset($this->backupInfo['maintruns']) && $this->backupInfo['maintruns'] > 1){
			$remove = array_slice($maintfiles,$this->backupInfo['maintruns'],null,true);
			foreach ($remove as $key => $value) {
				$this->log(sprintf("Removing %s",$value),'DEBUG');
				if($this->dryrun){
					continue;
				}
				$this->fs->remove($value);
			}
		}
	}

	public function processRemote(){
		$errors = [];
		foreach ($this->remoteStorage as $location) {
			$maintfiles = [];
			$location = explode('_', $location);
			try {
				$files = $this->freepbx->Filestore->ls($location[0],$location[1],$this->remotePath);
			} catch (\Exception $e) {
				$errors[] = $e->getMessage();
				$files = [];
			}
			foreach ($files as $file) {
				if(!isset($file['path'])){
					continue;
				}
				$parsed = $this->parseFile($file['basename']);
				if($parsed === false){
					continue;
				}
				$backupDate = Carbon::createFromTimestamp($parsed['timestamp'], 'UTC');
				if(isset($this->backupInfo['maintage']) && $this->backupInfo['maintage'] > 1){
					if($backupDate->diffInDays() > $backupInfo['maintage']){
						try {
							if($this->dryrun){
							 $this->log("\t".sprintf("UNLINK %s",$file['path']),'DEBUG');
								continue;
							}
							$this->fs->remove($location[0],$location[1],$file['path']);
							$this->fs->remove($location[0],$location[1],$file['path'].'.sha256sum');
						} catch (\Exception $e) {
							$errors[] = $e->getMessage();
							continue;
						}
						continue;
					}
				}
				if(!$parsed['isCheckSum']){
					$maintfiles[$parsed['timestamp']] = $file['path'];
				}
			}
			asort($maintfiles,SORT_NUMERIC);
			if(isset($this->backupInfo['maintruns']) && $this->backupInfo['maintruns'] > 1){
				$remove = array_slice($maintfiles,$this->backupInfo['maintruns'],null,true);
				foreach ($remove as $key => $value) {
					try {
						$this->log("\t".sprintf("Removing %s".PHP_EOL,$value),'DEBUG');
						if($this->dryrun){
							continue;
						}
						$this->fs->remove($location[0],$location[1],$value);
						$this->fs->remove($location[0],$location[1],$value.'.sha256sum');
					} catch (\Exception $e) {
						$errors[] = $e->getMessage();
						continue;
					}
				}
			}
		}
		return empty($errors)?true:$errors;
	}

	private function parseFile($filename){
		//20171012-130011-1507838411-15.0.1alpha1-42886857.tar.gz
		preg_match("/(\d{7})-(\d{6})-(\d{10,11})-(.*)-\d*\.tar\.gz(.sha256sum)?/", $filename, $output_array);
		$valid = false;
		$arraySize = sizeof($output_array);
		if($arraySize == 5){
			$valid = true;
		}
		if($arraySize == 6){
			$valid = true;
		}
		if(!$valid){
			return false;
		}
		return [
			'filename' => $output_array[0],
			'datestring' => $output_array[1],
			'timestring' => $output_array[2],
			'timestamp' => $output_array[3],
			'framework' => $output_array[4],
			'isCheckSum' => ($arraySize == 6)
		];
	}
}