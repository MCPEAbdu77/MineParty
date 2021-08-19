<?php

declare(strict_types=1);

namespace MCA7\MineParty;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\{command\Command, command\CommandSender, event\block\BlockBreakEvent, Player, Server};
use pocketmine\utils\TextFormat as C;
use pocketmine\utils\Config;
use onebone\economyapi\EconomyAPI;


class Main extends PluginBase implements Listener{

	public function onEnable()
	{
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->players = new Config($this->getDataFolder(). "blocks.yml");
		$this->db = new Config($this->getDataFolder(). "status.yml");
		$this->db->setNested("STATUS", "off");
        	$this->db->setNested("timeS", 0);
        	$this->db->save();
        	$this->players->save();
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
	{
		if($command->getName() === "mineparty")
		{
            	if(!isset($args[0])){
                	$sender->sendMessage(C::RED."Usage: /mineparty <join>");
                	return true;
           	 }
			switch ($args[0])
			{
				case 'join':
					if($sender instanceof Player)
					{
                        			if($this->db->getNested("STATUS") == "on")
                        			{
							$this->players->setNested($sender->getName(), 0);
							$sender->sendMessage("§l§eMine§fParty §7: §r§aJoined the MineParty§f! §aThe player with the highest number of blocks broken wins§f!");
                            				$this->getServer()->broadcastMessage(C::YELLOW.$sender->getName()." ".C::BLUE."has joined the MineParty!");
                            				$this->players->save();
                        			} else {
                            			$sender->sendMessage("§l§eMine§fParty §7: §r§cThere is no MineParty going on to join§f!");
                        			}
					} else {
						$this->getServer()->getLogger()->info(C::RED."Execute this command in-game!");
					}
					return true;
               			case 'start':
					if(!$sender instanceof Player OR $sender->hasPermission("mineparty.admin"))
					{
						$this->db->setNested("STATUS", "on");
                        			$this->db->save();
						$sender->sendMessage("§l§eMine§fParty §7: §r§bMineparty initiated§f!");
						$this->getServer()->broadcastMessage("§7===== §l§eMINE§fPARTY §aSTARTED §r§7=====");
						$this->getServer()->broadcastMessage("§bUse §c/mineparty join §bto join the game§f.");
						$this->getServer()->broadcastMessage("§bEnding in ".$this->getConfig()->get("minutes")." min(s).");
						$this->getServer()->broadcastMessage("§7===== §l========= ======= §r§7=====");
						$time_start = microtime(true);
						$this->db->setNested("timeS", (float)$time_start);
                        			$this->db->save();
					} else {
						$sender->sendMessage("§l§eMine§fParty §7: §r§cYou do not have the permission to use this command!");
						}
			}
            	return true;
		}
	}

	/**
	 * @priority MONITOR
	 */

	public function onBreak(BlockBreakEvent $e)
	{
		$name = $e->getPlayer()->getName();
		if($this->db->getNested("STATUS") === "on")
		{
            	$time_end = microtime(true);
		$time = $time_end - (float)$this->db->getNested("timeS");
            	$hours = (int)($time/60/60);
            	$minutes = (int)($time/60)-$hours*60;
		if($minutes >= (int)$this->getConfig()->get("minutes"))
		{
			$this->db->setNested("STATUS", "off");
                	$this->db->save();
			$this->getServer()->broadcastMessage("§7===== §l§eMINE§fPARTY §cENDED §r§7=====");
                	if($this->players->getAll() == null)
                	{
                    		$this->getServer()->broadcastMessage("§cNo players joined the event§f! \n §eJoin for the next MineParty by using \n §c/mineparty join §ewhen the event starts§f!");
                    		$this->getServer()->broadcastMessage("§7===== §l========= ===== §r§7=====");
                    		return;
                	}
                	$this->getServer()->broadcastMessage("§6Winner§e:");
			$winner = max(array_values($this->players->getAll()));
                	$player = array_search($winner, $this->players->getAll());
                	$this->getServer()->broadcastMessage("§b".$player.C::RED." : ".C::YELLOW.$winner." Blocks");
                	$reward = (int)$winner*1000;
                	$this->getServer()->broadcastMessage("§b".$player." §ewins §f$".$reward." §e($1000 per block mined)");
			$this->getServer()->broadcastMessage("§7===== §l========= ===== §r§7=====");
                	EconomyAPI::getInstance()->addMoney($player, (int)$reward);
                	unlink($this->getDataFolder()."blocks.yml");
        		unlink($this->getDataFolder()."status.yml");
                	$this->players = new Config($this->getDataFolder(). "blocks.yml");
			$this->db = new Config($this->getDataFolder(). "status.yml");
			$this->db->setNested("STATUS", "off");
       			$this->db->setNested("timeS", 0);
        		$this->db->save();
        		$this->players->save(); 
		}
		if(!$e->isCancelled())
		{
                	if($this->players->getNested($name) !== null)
                	{
				$this->players->setNested($name, $this->players->getNested($name)+1);
                		$this->players->save();
                		$e->getPlayer()->sendTip("§l§eMine§fParty §7: §a+1 Block§e!");
                	}
		}
		} 
	}
    
	
    	public function onDisable()
	{
        	unlink($this->getDataFolder()."blocks.yml");
        	unlink($this->getDataFolder()."status.yml");
     	}

}
