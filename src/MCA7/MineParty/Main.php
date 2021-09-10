<?php

declare(strict_types=1);

namespace MCA7\MineParty;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\{command\Command,
	command\CommandSender,
	event\block\BlockBreakEvent,
	event\player\PlayerQuitEvent,
	Player,
	Server};
use pocketmine\utils\TextFormat as C;
use onebone\economyapi\EconomyAPI;
use pocketmine\utils\Config;


class Main extends PluginBase implements Listener
{

	private $status = [];
	private $players = [];

	public function onEnable()
	{
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->status["STATUS"] = "off";
                $this->status["timeStart"] = 0;
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
	{
		if ($command->getName() === "mineparty") {
			if (!isset($args[0])) {
				$sender->sendMessage(C::RED . "Usage: /mineparty <join/quit>");
				return true;
			}
			switch ($args[0]) {
				case 'join':
					if ($sender instanceof Player) {
						if ($this->status["STATUS"] === "on") {
							$this->players[$sender->getName()] = 0;
							$sender->sendMessage("§l§eMine§fParty §7: §r§aJoined the MineParty§f! §aThe player with the highest number of blocks broken wins§f! 
							\n§cUse §e/mineparty quit §cto quit the game§7!");
							$this->getServer()->broadcastMessage(C::YELLOW . $sender->getName() . " " . C::BLUE . "has joined the MineParty!");
						} else {
							$sender->sendMessage("§l§eMine§fParty §7: §r§cThere is no MineParty going on to join§f!");
						}
					} else {
						$this->getServer()->getLogger()->info(C::RED . "Execute this command in-game!");
					}
					return true;
				case 'quit':
					if ($sender instanceof Player and $this->status["STATUS"] === "on") {
						if (isset($this->players[$sender->getName()])) {
							unset($this->players[$sender->getName()]);
							$sender->sendMessage("§e> §cYou have left the mineparty!");
							Server::getInstance()->broadcastMessage(C::YELLOW . $sender->getName() . " §9has left the MineParty!");
						} else {
							$sender->sendMessage("§c> You are not in any mineparty to quit!");
						}
					} else {
						$sender->sendMessage("§c> You have not joined any ongoing mineparty to quit it!");
					}
					return true;
				case 'start':
					if (!$sender instanceof Player or $sender->hasPermission("mineparty.admin")) {
						$this->status["STATUS"] = "on";
						$sender->sendMessage("§l§eMine§fParty §7: §r§bMineparty initiated§f!");
						$this->getServer()->broadcastMessage("§7===== §l§eMINE§fPARTY §aSTARTED §r§7===== 
						\n §bUse §c/mineparty join §bto join the game§f. 
						\n §8§l[§c!§8] §r§eMine the most number of blocks to win§7! 
						\n §bEnding in " . $this->getConfig()->get("minutes") . " min(s). 
						\n§7===== §l========= ======= §r§7=====");
						$time_start = microtime(true);
						$this->status["timeStart"] = (float)$time_start;
					} else {
						$sender->sendMessage("§l§eMine§fParty §7: §r§cYou do not have the permission to use this command!");
					}
			}
			return true;
		}
	}

	public function onQuit(PlayerQuitEvent $event)
	{
		if (isset($this->players[$event->getPlayer()->getName()])) {
			unset($this->players[$event->getPlayer()->getName()]);
			Server::getInstance()->broadcastMessage("§e" . $event->getPlayer()->getName() . " §0has left the mineparty§7!");
		}
	}

	/**
	 * @priority MONITOR
	 */

	public function onBreak(BlockBreakEvent $e)
	{
		$name = $e->getPlayer()->getName();
		if ($this->status["STATUS"] === "on") {
			$time_end = microtime(true);
			$time = $time_end - (float)$this->status["timeStart"];
			$hours = (int)($time / 60 / 60);
			$minutes = (int)($time / 60) - $hours * 60;
			if ($minutes >= (int)$this->getConfig()->get("minutes")) {
				$this->status["STATUS"] = "off";
				$this->status["timeStart"] = 0;
				$this->getServer()->broadcastMessage("§7===== §l§eMINE§fPARTY §cENDED §r§7=====");
				if ($this->players == null) {
					$this->getServer()->broadcastMessage(" §cNo players joined the event§f! 
                    			\n §eJoin for the next MineParty by using 
                    			\n §c/mineparty join §ewhen the event starts§f!
                    			\n§7===== §l========= ===== §r§7=====");
					return;
				}
				$winner = max($this->players);
				$player = array_search($winner, $this->players);
				$reward = (int)$winner * (int)$this->getConfig()->get("price-money");
				$this->getServer()->broadcastMessage(" §6Winner§e:
				\n §b" . $player . C::RED . " : " . C::YELLOW . $winner . " Blocks
				\n §b" . $player . " §ewins §f$" . $reward . " §e($" .$this->getConfig()->get("price-money"). " per block mined) 
				\n§7===== §l========= ===== §r§7=====");
				EconomyAPI::getInstance()->addMoney($player, (int)$reward);
				foreach ($this->players as $player) {
					unset($this->players[$player]);
				}
			}
			if (!$e->isCancelled()) {
				if (isset($this->players[$name])) {
					$this->players[$name] = $this->players[$name] + 1;
					$e->getPlayer()->sendTip("§l§eMine§fParty §7: §a+1 Block§e!");
				}
			}
		}
	}
}
