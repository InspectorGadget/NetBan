<?php

/**
*	
* .___   ________ 
* |   | /  _____/ 
* |   |/   \  ___ 
* |   |\    \_\  \
* |___| \______  /
*              \/ 
*
* All rights reserved InspectorGadget (c) 2018
*
*
**/

namespace InspectorGadget\NetworkBan;

use pocketmine\utils\Internet;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat as TF;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\event\Listener;

use InspectorGadget\NetworkBan\BanHandler;

class MainHandler extends PluginBase implements Listener {

	public $mysqli;
	public $kick_msg;
	public $api = "http://api.rtgnetwork.tk/mcpe/netban/";

	public function onEnable(): void {

		if (!is_dir($this->getDataFolder())) {
			@mkdir($this->getDataFolder());
			$this->getLogger()->info("Folder created..");
		}

		if (!is_file($this->getDataFolder() . "config.yml")) {
			$this->saveDefaultConfig();
		}

		if ($this->getConfig()->get("enable") === false) {
			$this->getLogger()->warning("Disabled in Config!");
		} else {
			$this->connectMySQL();
			$this->getServer()->getPluginManager()->registerEvents($this, $this);

			$this->mysqli->query("CREATE TABLE IF NOT EXISTS `banned`(`id` int(11) PRIMARY KEY AUTO_INCREMENT NOT NULL, `username` TEXT NOT NULL, `skin` TEXT NOT NULL, `ip` TEXT NOT NULL, `cid` TEXT NOT NULL, `bannedBy` TEXT NOT NULL)");
			$this->mysqli->query("CREATE TABLE IF NOT EXISTS `exempt` (`id` int(11) PRIMARY KEY AUTO_INCREMENT NOT NULL, `username` TEXT NOT NULL)");

			$this->getLogger()->info("Everything is setup!");
			// $this->checkForUpdates();
		}

	}

	public function checkForUpdates() {
        $decode = json_decode(Internet::getURL($this->api));
        $currentVersion = $this->getDescription()->getVersion();

        if ($currentVersion < $decode->version) {
            $this->getLogger()->warning("New update for NetBan is available! New Version: {$decode->version}");
        }

        if ($currentVersion == $decode->version) {
            $this->getLogger()->info("You are using the latest version of NetBan. Version {$decode->version}");
        }
    }

	public function returnBanHandler() {
		return new BanHandler($this, $this->mysqli);
	}

	public function returnKickMessage() {
		return $this->kick_msg;
	}

	public function connectMySQL() {
		if (strtolower($this->getConfig()->get("provider")) !== strtolower("MySQL")) {
			$this->getConfig()->set("provider", "MySQL");
			$this->getConfig()->save();
		} else {

			if (empty($this->getConfig()->get("kick-message"))) {
				$this->getConfig()->set("kick-message", "You have been NetBanned by %bannedby%");
				$this->getConfig()->save();
			}

			$this->kick_msg = $this->getConfig()->get("kick-message");

			$host = $this->getConfig()->get("host");
			$username = $this->getConfig()->get("username");
			$password = $this->getConfig()->get("password");
			$database = $this->getConfig()->get("database");

			if (empty($host) || empty($username) || empty($database)) {
				$this->getLogger()->warning("Please verify your SQL Credentials!");
			} else {
				$connection = mysqli_connect($host, $username, $password, $database);
				if (!$connection) {
					$this->getLogger()->warning("Unable to connect to MySQL");
					$this->getServer()->getPluginManager()->disablePlugin($this); // Shut down plugin
					exit();
				} else {
					$this->mysqli = $connection;
					$this->getLogger()->info("Connected to MySQL!");
				}
			}
		}	
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
		switch ($command->getName()) {
			case "nban":

				if ($sender->hasPermission("nban.command")) {

					if (isset($args[0])) {
						switch (strtolower($args[0])) {

							case "help":

								$sender->sendMessage(TF::GREEN . "Commands: \n - /nban ban {player} | Player must be online! \n - /nban pardon {player} | Unbans a player!");

								return true;
							break;

							case "ban":

								if (isset($args[1])) {
									$value = $args[1];
									$player = $this->getServer()->getPlayer($value);
									if ($player instanceof Player) {
										$this->returnBanHandler()->banPlayer($player, $sender);
									} else {
										$sender->sendMessage(TF::RED . "$value isn't a valid Player!");
									}
								} else {
									$sender->sendMessage(TF::GREEN . "[Usage] /nban ban {player}");
								}

								return true;
							break;

							case "pardon":

								if (isset($args[1])) {

									$value = $args[1];
									$this->returnBanHandler()->pardonPlayer($value, $sender);

								} else {
									$sender->sendMessage(TF::GREEN . "[Usage] /nban pardon {username}");
								}

								return true;
							break;

						}
					} else {
						$sender->sendMessage(TF::GREEN . "[Usage] /nban help");
					}

				} else {
					$sender->sendMessage(TF::RED . "You have no permission to use this command!");
				}

				return true;
			break;
		}
	}

	public function onJoin(PlayerPreLoginEvent $event) {
		$player = $event->getPlayer();
		$username = strtolower($player->getName());
		$ip = $player->getAddress();
		$cid = $player->getClientId();
		$skin = $player->getSkin()->getSkinData();
		$data = $this->returnBanHandler()->getBannedData($username);

		$bannedBy = $data['bannedBy'];
		if ($ip === $data['ip'] || $cid === $data['cid'] || $username === $data['username'] || $skin === $data['skin']) {
			$player->kick(str_replace("%bannedby%", $bannedBy, $this->returnKickMessage()));
			$event->setCancelled();
		}
	}

	public function onDisable(): void {
		if (isset($this->mysqli)) {
			$this->mysqli->close();
		}
		$this->getLogger()->info("Properly shutting down NetBan!");
	}

}