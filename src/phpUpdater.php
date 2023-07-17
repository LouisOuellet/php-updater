<?php

//Declaring namespace
namespace LaswitchTech\phpUpdater;

//Import phpConfigurator class into the global namespace
use LaswitchTech\phpConfigurator\phpConfigurator;

//Import phpLogger class into the global namespace
use LaswitchTech\phpLogger\phpLogger;

// Importing Dependencies
use DateTime;
use Exception;

class phpUpdater {

    // Configurator
    private $Configurator = null;

    // Logger
    private $Logger = null;

    // Database
    private $Database = null;

    // Properties
    private $URL = null;
    private $Token = null;
    private $Owner = null;
    private $Repository = null;
    private $Version = null;
    private $ID = null;
    private $Latest = null;
    private $Releases = null;

    /**
     * Create a new phpUpdater instance.
     *
     * @param  string|array|null  $logFile
     * @return void
     * @throws Exception
     */
    public function __construct(){

        // Initialize Configurator
        $this->Configurator = new phpConfigurator('updater');

        // Initialize Configurator
        $this->Logger = new phpLogger();

        // Retrieve Updater Settings
        $this->Token = $this->Configurator->get('updater', 'token') ?: $this->Token;
        $this->Owner = $this->Configurator->get('updater', 'owner') ?: $this->Owner;
        $this->Repository = $this->Configurator->get('updater', 'repository') ?: $this->Repository;
        $this->ID = $this->Configurator->get('updater', 'id') ?: $this->ID;

        // Retrieve Latest Release
        $this->fetch();

        // Check if phpDatabase is installed
        if (class_exists('LaswitchTech\phpDatabase\phpDatabase')) {
            $this->Database = new \LaswitchTech\phpDatabase\phpDatabase();
        }
    }

    /**
     * Configure Library.
     *
     * @param  string  $option
     * @param  bool|int  $value
     * @return void
     * @throws Exception
     */
    public function config($option, $value){
        if(is_string($option)){
            switch($option){
                case"token":
                case"owner":
                case"repository":
                case"version":
                    if(is_string($value)){
                        // Save to Configurator
                        $this->Configurator->set('updater',$option, $value);
                    } else{
                        throw new Exception("2nd argument must be a string.");
                    }
                    break;
                case"id":
                    if(is_int($value)){
                        // Save to Configurator
                        $this->Configurator->set('updater',$option, $value);
                    } else{
                        throw new Exception("2nd argument must be an integer.");
                    }
                    break;
                default:
                    throw new Exception("unable to configure $option.");
                    break;
            }
        } else{
            throw new Exception("1st argument must be as string.");
        }

        return $this;
    }

    /**
     * Retrieve the latest release.
     *
     * @return string
     * @throws Exception
     */
    private function fetch(){
        try{

            // Check if Token is set
            if($this->Token == null){
                throw new Exception("Token is not set.");
            }

            // Check if Owner is set
            if($this->Owner == null){
                throw new Exception("Owner is not set.");
            }

            // Check if Repository is set
            if($this->Repository == null){
                throw new Exception("Repository is not set.");
            }

            // Check if URL is set
            if($this->URL == null){
                throw new Exception("URL is not set.");
            }

            // Build URL
            $this->URL = "https://api.github.com/repos/" . $this->Owner . "/" . $this->Repository;

            // Initialize cURL
            $curl = curl_init($this->URL . "/releases/latest");
            curl_setopt($curl, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->Token,
                'User-Agent: PHP'
            ]);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

            // Execute cURL
            $response = curl_exec($curl);

            // Close cURL
            curl_close($curl);

            if ($response) {

                // Save to Object
                $this->Latest = json_decode($response, true);

                // Initialize cURL
                $curl = curl_init($this->URL . "/releases");
                curl_setopt($curl, CURLOPT_HTTPHEADER, [
                    'Authorization: Bearer ' . $this->Token,
                    'User-Agent: PHP'
                ]);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    
                // Execute cURL
                $response = curl_exec($curl);
    
                // Close cURL
                curl_close($curl);

                if ($response) {

                    // Save to Object
                    $this->Releases = json_decode($response, true);
                } else {
    
                    // Handle any errors that occurred during the request
                    throw new Exception("Failed to retrieve the releases.");
                }
            } else {

                // Handle any errors that occurred during the request
                throw new Exception("Failed to retrieve the latest release.");
            }
        } catch (Exception $e) {
            $this->Logger->error('Error: '.$e->getMessage());
        }
    }

    /**
     * Check for Updates.
     *
     * @return bool
     * @throws Exception
     */
    public function check(){

        // Check if Latest is set and if the latest release is newer than the current version
        return $this->Latest && $this->Latest['id'] > $this->ID;
    }

    /**
     * Backup the current version.
     *
     * @return string
     * @throws Exception
     */
    public function backup($filename = null, $exclude = ['tmp', 'backup']){
        try{

            // Check if phpDatabase is installed
            if($this->Database){

                // Backup Database
                $this->Database->backup();
            }

            // Set Path to Backup Folder
            $path = $this->Configurator->root() . "/backup";

			// If no filename was provided, generate a filename
			if (is_null($filename)) {
				$filename = time() . ".zip";
			} else {
				$filename = $filename . ".zip";
			}

			$this->Logger->info("Creating backup to: " . $path . "/" . $filename);

			// Create the directory recursively
			if(!is_dir(dirname($path . "/" . $filename))){
				mkdir(dirname($path . "/" . $filename), 0777, true);
			}
    
            // Create a new zip archive
            $zip = new ZipArchive();
            $zip->open($path . '/' . $filename, ZipArchive::CREATE | ZipArchive::OVERWRITE);

            // Iterate over all files and directories in the root folder
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->Configurator->root(), RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
            foreach ($iterator as $file) {
                $filePath = $file->getPathname();
                $relativePath = substr($filePath, strlen($this->Configurator->root()) + 1);
    
                // Skip excluded folders
                if (in_array($file->getBasename(), $exclude) || in_array($relativePath, $exclude)) {
                    continue;
                }
        
                // Add file or directory to the zip archive
                if ($file->isFile()) {
                    $zip->addFile($filePath, $relativePath);
                } elseif ($file->isDir()) {
                    $zip->addEmptyDir($relativePath);
                }
            }
    
            // Close the zip archive
            $zip->close();
            
            // Return the path to the backup file
            return $path . '/' . $filename;
        } catch (Exception $e) {
            $this->Logger->error('Error: '.$e->getMessage());
        }
    }

    /**
     * Restore from a backup.
     *
     * @return string
     * @throws Exception
     */
    public function restore(){
        try{} catch (Exception $e) {
            $this->Logger->error('Error: '.$e->getMessage());
        }
    }

    /**
     * Update the current version.
     *
     * @return void
     * @throws Exception
     */
    public function update(){
        try{} catch (Exception $e) {
            $this->Logger->error('Error: '.$e->getMessage());
        }
    }

    /**
     * Rollback to the last backup.
     *
     * @return void
     * @throws Exception
     */
    public function rollback(){
        try{} catch (Exception $e) {
            $this->Logger->error('Error: '.$e->getMessage());
        }
    }
}
