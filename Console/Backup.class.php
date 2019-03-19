<?php
namespace FreePBX\Console\Command;
use FreePBX\modules\Backup\Handlers as Handler;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Command\LockableTrait;
use function FreePBX\modules\Backup\Json\json_decode;
use function FreePBX\modules\Backup\Json\json_encode;
class Backup extends Command {
	use LockableTrait;

	protected function configure(){
		$this->setName('backup')
		->setAliases(array('bu'))
		->setDescription('Run backup and restore jobs')
		->setDefinition(array(
				new InputOption('backup', '', InputOption::VALUE_REQUIRED, 'Backup ID'),
				new InputOption('externbackup', '', InputOption::VALUE_REQUIRED, 'Base64 encoded backup job'),
				new InputOption('dumpextern', '', InputOption::VALUE_REQUIRED, 'Dump Base64 backup data'),
				new InputOption('transaction', '', InputOption::VALUE_REQUIRED, 'Transaction ID for the backup'),
				new InputOption('list', '', InputOption::VALUE_NONE, 'List backups'),
				new InputOption('warmspare', '', InputOption::VALUE_NONE, 'Set the warmspare flag'),
				new InputOption('implemented', '', InputOption::VALUE_NONE, ''),
				new InputOption('filestore', '', InputOption::VALUE_REQUIRED, 'Use filestore ID to restore a file'),
				new InputOption('restore', '', InputOption::VALUE_REQUIRED, 'Restore File'),
				new InputOption('modules', '', InputOption::VALUE_REQUIRED, 'Specific Modules to restore from using --restore, separate each module by a comma'),
				new InputOption('restoresingle', '', InputOption::VALUE_REQUIRED, 'Module backup to restore'),
				new InputOption('backupsingle', '', InputOption::VALUE_REQUIRED, 'Module to backup'),
				new InputOption('singlesaveto', '', InputOption::VALUE_REQUIRED, 'Where to save the single module backup.'),
				new InputOption('b64import', '', InputOption::VALUE_REQUIRED, ''),
				new InputOption('fallback', '', InputOption::VALUE_NONE, ''),
		))
		->setHelp('Run a backup: fwconsole backup --backup [backup-id]'.PHP_EOL
		.'Run a restore: fwconsole backup --restore [/path/to/restore-xxxxxx.tar.gz]'.PHP_EOL
		.'List backups: fwconsole backup --list'.PHP_EOL
		.'Dump remote backup string: fwconsole backup --dumpextern [backup-id]'.PHP_EOL
		.'Run backup job with remote string: fwconsole backup --externbackup [Base64encodedString]'.PHP_EOL
		.'Run backup job with remote string and custom transaction id: fwconsole backup --externbackup [Base64encodedString] --transaction [yourstring]'.PHP_EOL
		.'Run backup on a single module: fwconsole backup --backupsingle [modulename] --singlesaveto [output/path]'.PHP_EOL
		.'Run a single module backup: fwconsole backup --restoresingle [filename]'.PHP_EOL
		);
	}
	protected function execute(InputInterface $input, OutputInterface $output){
		$this->freepbx = \FreePBX::Create();
		$this->Backup = $this->freepbx->Backup;

		if(posix_getuid() === 0) {
			$AMPASTERISKWEBUSER = $this->freepbx->Config->get('AMPASTERISKWEBUSER');
			$info = posix_getpwnam($AMPASTERISKWEBUSER);
			if(empty($info)) {
				$output->writeln("$AMPASTERISKWEBUSER is not a valid user");
				return 0;
			}
			posix_setuid($info['uid']);
		}

		if (!$this->lock()) {
			$output->writeln('The command is already running in another process.');
			return 0;
		}

		$this->output = $output;
		$this->input = $input;
		$this->freepbx->Backup->output = $output;
		$list = $input->getOption('list');
		$warmspare = $input->getOption('warmspare');
		$backup = $input->getOption('backup');
		$filestore = $input->getOption('filestore');
		$restore = $input->getOption('restore');
		$remote = $input->getOption('externbackup');
		$dumpextern = $input->getOption('dumpextern');
		$transaction = $input->getOption('transaction');
		$backupsingle = $input->getOption('backupsingle');
		$restoresingle = $input->getOption('restoresingle');
		$b64import = $input->getOption('b64import');
		if($b64import){
			return $this->addBackupByString($b64import);
		}

		if($input->getOption('implemented')){
			$backupHandler = new Handler\Backup($this->freepbx);
			$output->writeln(json_encode($backupHandler->getModules()));
			return;
		}

		if($transaction) {
			$transactionid = $transaction;
		} else {
			$transactionid = $this->freepbx->Backup->generateID();
			$output->writeln(sprintf(_("Transaction ID is: %s"),$transactionid));
		}

		switch (true) {
			case $backupsingle:
				$saveto = $input->getOption('singlesaveto')?$input->getOption('singlesaveto'):'';
				$saveto = !empty($saveto) ? $saveto : rtrim(getcwd());
				$backupHandler = new Handler\Backup\Single($this->freepbx, $saveto, $transactionid, posix_getpid());
				if($input->getOption('fallback')){
					$backupHandler->setDefaultFallback(true);
				}
				$backupHandler->setModule($backupsingle);
				$backupHandler->process();
				$errors = $backupHandler->getErrors();
				$warnings = $backupHandler->getWarnings();
				if(empty($errors) && empty($warnings)) {
					$output->writeln(_("Backup completed successfully"));
				} else {
					if(!empty($errors)) {
						$output->writeln(_("There were errors during the backup process"));
						foreach($errors as $error) {
							$output->writeln("\t<error>".$error."</error>");
						}
					}
					if(!empty($warnings)) {
						$output->writeln(_("There were warnings during the backup process"));
						foreach($warnings as $warning) {
							$output->writeln("\t<comment>".$warning."</comment>");
						}
					}
				}
				return;
			break;
			case $restoresingle:
				$restoreHandler = new Handler\Restore\Single($this->freepbx, $restoresingle, $transactionid, posix_getpid());
				if($input->getOption('fallback')){
					$restoreHandler->setDefaultFallback(true);
				}
				$restoreHandler->process();
				$errors = $restoreHandler->getErrors();
				$warnings = $restoreHandler->getWarnings();
				if(empty($errors) && empty($warnings)) {
					$output->writeln(_("Restore completed successfully"));
				} else {
					if(!empty($errors)) {
						$output->writeln(_("There were errors during the restore process"));
						foreach($errors as $error) {
							$output->writeln("\t<error>".$error."</error>");
						}
					}
					if(!empty($warnings)) {
						$output->writeln(_("There were warnings during the restore process"));
						foreach($warnings as $warning) {
							$output->writeln("\t<comment>".$warning."</comment>");
						}
					}
				}
			break;
			case $list:
				$this->listBackups();
				return;
			break;
			case $backup:
				$buid = $input->getOption('backup');
				$item = $this->freepbx->Backup->getBackup($buid);
				if(empty($item)) {
					throw new \Exception("Invalid backup id!");
				}

				$running = $this->freepbx->Backup->getConfig($buid,"runningBackupJobs");
				if(!empty($running) && posix_getpgid($running['pid']) !== false) {
					throw new \Exception("This backup is already running!");
				}

				$this->freepbx->Backup->setConfig($buid,["pid" => posix_getpid(), "transaction" => $transactionid],"runningBackupJobs");

				$backupHandler = new Handler\Backup\Multiple($this->freepbx, $buid, $transactionid, posix_getpid());
				if($input->getOption('fallback')){
					$backupHandler->setDefaultFallback(true);
				}
				$backupHandler->process();

				$maintenanceHandler = new Handler\Backup\Maintenance($this->freepbx, $buid, $transactionid, posix_getpid());
				$output->writeln(_("Performing Local Maintenance"));
				$maintenanceHandler->processLocal();
				$output->writeln(_("Finished Local Maintenance"));
				$output->writeln(_("Performing Remote Maintenance"));
				$maintenanceHandler->processRemote();
				$output->writeln(_("Finished Remote Maintenance"));

				$storageHandler = new Handler\Storage($this->freepbx, $buid, $transactionid, posix_getpid(), $backupHandler->getFile());
				$storageHandler->process();

				$errors = array_merge($backupHandler->getErrors(),$maintenanceHandler->getErrors(),$storageHandler->getErrors());
				$warnings = array_merge($backupHandler->getWarnings(),$maintenanceHandler->getWarnings(),$storageHandler->getWarnings());

				if(empty($errors) && empty($warnings)) {
					$output->writeln(_("Backup completed successfully"));
				} else {
					if(!empty($errors)) {
						$output->writeln(_("There were errors during the backup process"));
						foreach($errors as $error) {
							$output->writeln("\t<error>".$error."</error>");
						}
					}
					if(!empty($warnings)) {
						$output->writeln(_("There were warnings during the backup process"));
						foreach($warnings as $warning) {
							$output->writeln("\t<comment>".$warning."</comment>");
						}
					}
				}

				$this->freepbx->Backup->delConfig($buid,"runningBackupJobs");
				return;
			break;
			case $filestore:
				$info = $this->freepbx->Filestore->getItemById($filestore);
				if(empty($info)) {
					throw new \Exception('Invalid filestore id');
				}
				$output->write(sprintf(_("Retrieving %s from %s:%s..."),basename($restore), $info['driver'],$info['name']));
				$path = sys_get_temp_dir().'/backup/'.basename($restore);
				$this->freepbx->Filestore->download($filestore,$restore,$path);
				$output->writeln(_('Done'));
				$restore = $path;
			case $restore:
				if(!file_exists($restore)) {
					throw new \Exception("File $restore does not exist or can not be found!");
				}

				$running = $this->freepbx->Backup->getConfig("runningRestoreJob");
				if(!empty($running) && posix_getpgid($running['pid']) !== false) {
					throw new \Exception("There is a restore already running!");
				}

				$this->freepbx->Backup->setConfig("runningRestoreJob",["pid" => posix_getpid(), "transaction" => $transactionid, "fileid" => md5($restore)]);

				$output->write(_("Determining backup file type..."));
				$backupType = $this->freepbx->Backup->determineBackupFileType($restore);
				if($backupType === false){
					throw new \Exception('Unknown file type');
				}
				$output->writeln(sprintf(_("type is %s"),$backupType));
				$pid = posix_getpid();
				if($backupType === 'current'){
					$restoreHandler = new Handler\Restore\Multiple($this->freepbx,$restore,$transactionid, posix_getpid());
				}
				if($backupType === 'legacy'){
					$restoreHandler = new Handler\Restore\Legacy($this->freepbx,$restore, $transactionid, posix_getpid());
				}
				if($input->getOption('fallback')){
					$restoreHandler->setDefaultFallback(true);
				}
				if($input->getOption('modules')) {
					$restoreHandler->setSpecificRestore(explode(",",$input->getOption('modules')));
				}
				$output->writeln(sprintf('Starting restore job with file: %s',$restore));
				$restoreHandler->process();

				$errors = $restoreHandler->getErrors();
				$warnings = $restoreHandler->getWarnings();
				if(empty($errors) && empty($warnings)) {
					$output->writeln(_("Restore completed successfully"));
				} else {
					if(!empty($errors)) {
						$output->writeln(_("There were errors during the restore process"));
						foreach($errors as $error) {
							$output->writeln("\t<error>".$error."</error>");
						}
					}
					if(!empty($warnings)) {
						$output->writeln(_("There were warnings during the restore process"));
						foreach($warnings as $warning) {
							$output->writeln("\t<comment>".$warning."</comment>");
						}
					}
				}
				$this->freepbx->Backup->delConfig("runningRestoreJob");
			break;
			case $dumpextern:
				$backupdata = $this->freepbx->Backup->getBackup($input->getOption('dumpextern'));
				if(!$backupdata){
					$output->writeln("Could not find the backup specified please check the id.");
					return false;
				}
				$backupdata['backup_items'] = $this->freepbx->Backup->getAll('modules_'.$input->getOption('dumpextern'));
				$output->writeln(base64_encode(json_encode($backupdata)));
				return true;
			break;
			case $remote:
				$job = $transaction?$transaction:$this->freepbx->Backup->generateID();
				$output->writeln(sprintf('Starting backup job with ID: %s',$job));
				$pid = posix_getpid();
				$errors  = $backupHandler->process('',$job,$input->getOption('externbackup'),$pid);
			break;
			default:
				$output->writeln($this->getHelp());
			break;
		}

	}
	public function listBackups(){
		$this->output->writeln("fwconsole backup --backup [Backup ID]");
		$table = new Table($this->output);
		$table->setHeaders(['Backup Name','Description','Backup ID']);
		$list = [];
		foreach ($this->freepbx->Backup->listBackups() as $value) {
			$list[] = [$value['name'],$value['description'],$value['id']];
		}
		$table->setRows($list);
		$table->render();
	}

	public function addBackupByString($base64){
		$data = json_decode(base64_decode($base64), true);
		if(json_last_error() !== JSON_ERROR_NONE){
			$this->output->writeln(sprintf('Backup could not be imorted: %s',json_last_error_msg()));
			return false;
		}
		$items = [];
		if(isset($data['backup_items'])){
			$items = $data['backup_items'];
			unset($data['backup_items']);
		}
		$id = $this->freepbx->Backup->generateID();

		foreach($data as $key => $value){
			$this->freepbx->Backup->updateBackupSetting($id,$key,$value);
		}
		$this->freepbx->Backup->setModulesById($id, $items);
		$this->freepbx->Backup->setConfig($id, array('id' => $id, 'name' => $data['backup_name'], 'description' => $data['backup_description']), 'backupList');
		$this->output->writeln(sprintf('Backup created ID: %s', $id));
		return $id;
	}
}
