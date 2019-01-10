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

use InspectorGadget\NetworkBan\MainHandler;

use pocketmine\utils\TextFormat as TF;
use pocketmine\Player;

class BanHandler {

	public $plugin;
	public $exempt;
	public $database;

	public $banTable = "banned";
	public $exemptTable = "exempt";

	public function __construct(MainHandler $plugin, $database) {
		$this->plugin = $plugin;
		$this->database = $database;
	}

	public function isExempted($username) {
		$username = strtolower($username);
		$sql = "SELECT * FROM $this->exemptTable WHERE username = ?";
		$stmt = mysqli_stmt_init($this->database);

		if (!mysqli_stmt_prepare($stmt, $sql)) {
			$sender->sendMessage(TF::RED . "MySQL Error: Exempt Check");
			return true;
		} else {
			mysqli_stmt_bind_param($stmt, "s", $username);
			mysqli_stmt_execute($stmt);
			$store = mysqli_stmt_get_result($stmt);
			$row = mysqli_num_rows($store);

			if ($row > 0) {
				return true;
			} else {
				return false;
			}
		}
	}

	public function isBanned($playerName) {
		$playerName = strtolower($playerName);
		$sql = "SELECT * FROM $this->banTable WHERE username = ?";
		$stmt = mysqli_stmt_init($this->database);

		if (!mysqli_stmt_prepare($stmt, $sql)) {
			$plugin->getLogger()->info("MySQL Error : Is Banned Check");
			return true; // Cut Off
		} else {
			mysqli_stmt_bind_param($stmt, "s", $playerName);
			mysqli_stmt_execute($stmt);
			$store = mysqli_stmt_get_result($stmt);
			$row = mysqli_num_rows($store);

			if ($row > 0) {
				return true;
			} else {
				return false;
			}
		}
	}

	public function banPlayer(Player $player, $sender) {
		$raw_username = $player->getName();
		$username = strtolower($player->getName());
		$ip = $player->getAddress();
		$cid = $player->getClientId();
		$skin = $player->getSkin()->getSkinData();
		$bannedBy = $sender->getName();

		if (!$this->isExempted($username)) {

			$sql = "INSERT INTO $this->banTable(username, skin, ip, cid, bannedBy) VALUES (?, ?, ?, ?, ?)";
			$stmt = mysqli_stmt_init($this->database);

			if (!mysqli_stmt_prepare($stmt, $sql)) {
				$sender->sendMessage("MySQL Error : Ban Player");
				return true;
			} else {
				mysqli_stmt_bind_param($stmt, "sssss", $username, $skin, $ip, $cid, $bannedBy);
				$result = mysqli_stmt_execute($stmt);

				if ($result) {
					$sender->sendMessage(TF::GREEN . "Player named $raw_username has been Network Banned!");
					$player->kick(str_replace("%bannedby%", $bannedBy, $this->plugin->returnKickMessage()));
				} else {
					$sender->sendMessage(TF::RED . "Our Clusters are encumbered, try again later!");
				}
			}

		} else {
			$sender->sendMessage(TF::RED . "Username $raw_username is in the exempt list!");
		}
	}

	public function pardonPlayer($playerName, $sender) {
		if ($this->isBanned($playerName, $sender)) {
			$username = strtolower($playerName);
			$sql = "DELETE FROM $this->banTable WHERE username = ?";
			$stmt = mysqli_stmt_init($this->database);

			if (!mysqli_stmt_prepare($stmt, $sql)) {
				$sender->sendMessage(TF::RED . "MySQL Error: Pardon Player");
				return true;
			} else {
				mysqli_stmt_bind_param($stmt, "s", $username);
				$result = mysqli_stmt_execute($stmt);

				if ($result) {
					$sender->sendMessage(TF::GREEN . "Player named $playerName has been pardoned!");
				} else {
					$sender->sendMessage(TF::RED . "Our Clusters are encumbered, try again later!");
				}
			}
		} else {
			$sender->sendMessage(TF::RED . "This user isn't banned!");
		}
	}

	public function getBannedData($playerName) {
		if ($this->isBanned($playerName)) {
			$username = strtolower($playerName);
			$sql = "SELECT * FROM $this->banTable WHERE username = ?";
			$stmt = mysqli_stmt_init($this->database);

			if (!mysqli_stmt_prepare($stmt, $sql)) {
				$this->getLogger()->info(TF::RED . "MySQL Error: Get Banned Data");
				return true;
			} else {
				mysqli_stmt_bind_param($stmt, "s", $username);
				mysqli_stmt_execute($stmt);
				$store = mysqli_stmt_get_result($stmt);

				if ($row = mysqli_fetch_assoc($store)) {
					return $row;
				}
			}
		}
	}

}