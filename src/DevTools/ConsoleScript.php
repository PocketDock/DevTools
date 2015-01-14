<?php

/*
 * DevTools plugin for PocketMine-MP
 * Copyright (C) 2014 PocketMine Team <https://github.com/PocketMine/DevTools>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
*/

class PluginDescription {
    private $name;
    private $main;
    private $api;
    private $depend = [];
    private $softDepend = [];
    private $loadBefore = [];
    private $version;
    private $commands = [];
    private $description = null;
    private $authors = [];
    private $website = null;
    private $prefix = null;
    private $order = 1;
    /**
     * @param string $yamlString
     */
    public function __construct($yamlString) {
        $this->loadMap(yaml_parse($yamlString)); //TODO compile a binary with YAML

    }
    /**
     * @param array $plugin
     *
     * @throws \Exception
     */
    private function loadMap(array $plugin) {
        $this->name = preg_replace("[^A-Za-z0-9 _.-]", "", $plugin["name"]);
        if ($this->name === "") {
            throw new \Exception("Invalid PluginDescription name");
        }
        $this->name = str_replace(" ", "_", $this->name);
        $this->version = $plugin["version"];
        $this->main = $plugin["main"];
        $this->api = !is_array($plugin["api"]) ? array($plugin["api"]) : $plugin["api"];
        if (stripos($this->main, "pocketmine\\") === 0) {
            trigger_error("Invalid PluginDescription main, cannot start within the PocketMine namespace", E_USER_ERROR);
            return;
        }
        if (isset($plugin["commands"]) and is_array($plugin["commands"])) {
            $this->commands = $plugin["commands"];
        }
        if (isset($plugin["depend"])) {
            $this->depend = (array)$plugin["depend"];
        }
        if (isset($plugin["softdepend"])) {
            $this->softDepend = (array)$plugin["softdepend"];
        }
        if (isset($plugin["loadbefore"])) {
            $this->loadBefore = (array)$plugin["loadbefore"];
        }
        if (isset($plugin["website"])) {
            $this->website = $plugin["website"];
        }
        if (isset($plugin["description"])) {
            $this->description = $plugin["description"];
        }
        if (isset($plugin["prefix"])) {
            $this->prefix = $plugin["prefix"];
        }
        if (isset($plugin["load"])) {
            $order = strtoupper($plugin["load"]);
            if ($order == "STARTUP") $this->order = 0;
            else $this->order = 1;
        }
        $this->authors = [];
        if (isset($plugin["author"])) {
            $this->authors[] = $plugin["author"];
        }
        if (isset($plugin["authors"])) {
            foreach ($plugin["authors"] as $author) {
                $this->authors[] = $author;
            }
        }
    }
    /**
     * @return string
     */
    public function getFullName() {
        return $this->name . " v" . $this->version;
    }
    /**
     * @return array
     */
    public function getCompatibleApis() {
        return $this->api;
    }
    /**
     * @return array
     */
    public function getAuthors() {
        return $this->authors;
    }
    /**
     * @return string
     */
    public function getPrefix() {
        return $this->prefix;
    }
    /**
     * @return array
     */
    public function getCommands() {
        return $this->commands;
    }
    /**
     * @return array
     */
    public function getDepend() {
        return $this->depend;
    }
    /**
     * @return string
     */
    public function getDescription() {
        return $this->description;
    }
    /**
     * @return array
     */
    public function getLoadBefore() {
        return $this->loadBefore;
    }
    /**
     * @return string
     */
    public function getMain() {
        return $this->main;
    }
    /**
     * @return string
     */
    public function getName() {
        return $this->name;
    }
    /**
     * @return int
     */
    public function getOrder() {
        return $this->order;
    }
    /**
     * @return array
     */
    public function getSoftDepend() {
        return $this->softDepend;
    }
    /**
     * @return string
     */
    public function getVersion() {
        return $this->version;
    }
    /**
     * @return string
     */
    public function getWebsite() {
        return $this->website;
    }
}

function downloadPlugin($depend) {
    $pluginSearch = json_decode(file_get_contents('http://sleepy-wave-2826.herokuapp.com/autocomplete?q=' . $depend), true) ['plugin-suggest'][0]['options'];
    if (empty($pluginSearch)) {
        #TODO: Exit when dependency isn't found?
        echo "\nPlugin " . $depend . " not found in plugin repo" . "\n";
        return;
    }
    if (count($pluginSearch) > 1) {
        #TODO: Exit when dependency isn't found?
        echo "\nMore than one plugin matches the search for " . $depend . "\n";
        return;
    }
    $payload = $pluginSearch[0]['payload'];
    $phar = file_get_contents('http://forums.pocketmine.net/plugins/' . $payload['resource_id'] . '/download?version=' . $payload['current_version_id']);
    return file_put_contents('/pocketmine/plugins/' . $depend . '.phar', $phar);
}

$opts = getopt("", ["make:", "relative:", "out:", "entry:", "compress"]);

if (!isset($opts["make"])) {
    echo "== PocketMine-MP DevTools CLI interface ==\n\n";
    echo "Usage: " . PHP_BINARY . " -dphar.readonly=0 " . $argv[0] . " --make <sourceFolder> --relative <relativePath> --entry \"relativeSourcePath.php\" --out <pharName.phar>\n";
    exit(0);
}

if (ini_get("phar.readonly") == 1) {
    echo "Set phar.readonly to 0 with -dphar.readonly=0\n";
    exit(1);
}

$folderPath = rtrim(str_replace("\\", "/", realpath($opts["make"])), "/") . "/";
$relativePath = isset($opts["relative"]) ? rtrim(str_replace("\\", "/", realpath($opts["relative"])), "/") . "/" : $folderPath;
$pharName = isset($opts["out"]) ? $opts["out"] : "output.phar";

if (!is_dir($folderPath)) {
    echo $folderPath . " is not a folder\n";
    exit(1);
}
$description = new PluginDescription(file_get_contents($folderPath . "plugin.yml"));
echo "\nCreating " . $pharName . "...\n";
$phar = new \Phar($pharName);
$phar->setMetadata(["name" => $description->getName(), "version" => $description->getVersion(), "main" => $description->getMain(), "api" => $description->getCompatibleApis(), "depend" => $description->getDepend(), "description" => $description->getDescription(), "authors" => $description->getAuthors(), "website" => $description->getWebsite(), "creationDate" => time() ]);
if (isset($opts["entry"]) and $opts["entry"] != null) {
    $entry = addslashes(str_replace("\\", "/", $opts["entry"]));
    echo "Setting entry point to " . $entry . "\n";
    $phar->setStub('<?php require("phar://". __FILE__ ."/' . $entry . '"); __HALT_COMPILER();');
} else {
    echo "No entry point set\n";
    $phar->setStub('<?php echo "PocketMine-MP plugin ' . $description->getName() . ' v' . $description->getVersion() . '\nThis file has been generated using PocketDock Builder at ' . date("r") . '\n----------------\n";if(extension_loaded("phar")){$phar = new \Phar(__FILE__);foreach($phar->getMetadata() as $key => $value){echo ucfirst($key).": ".(is_array($value) ? implode(", ", $value):$value)."\n";}} __HALT_COMPILER();');
}
$phar->setSignatureAlgorithm(\Phar::SHA1);
$phar->startBuffering();
echo "Adding files...\n";
$maxLen = 0;
$count = 0;
foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($folderPath)) as $file) {
    $path = rtrim(str_replace(["\\", $relativePath], ["/", ""], $file), "/");
    if ($path{0} === "." or strpos($path, "/.") !== false) {
        continue;
    }
    $phar->addFile($file, $path);
    if (strlen($path) > $maxLen) {
        $maxLen = strlen($path);
    }
    echo "\r[" . (++$count) . "] " . str_pad($path, $maxLen, " ");
}
if (isset($opts["compress"])) {
    echo "\nCompressing...\n";
    $phar->compressFiles(\Phar::GZ);
}
$phar->stopBuffering();

if (!empty($description->getDepend()) && $description->getDepend() != []) {
    mkdir('/pocketmine/plugins');
    foreach ($description->getDepend() as $depend) {
        downloadPlugin($depend);
    }
}
if (!empty($description->getSoftDepend()) && $description->getSoftDepend() != []) {
    mkdir('/pocketmine/plugins');
    foreach ($description->getSoftDepend() as $depend) {
        downloadPlugin($depend);
    }
}

echo "Done!\n";
exit(0);
