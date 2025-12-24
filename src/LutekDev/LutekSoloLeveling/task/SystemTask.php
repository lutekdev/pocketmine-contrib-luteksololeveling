<?php

declare( strict_types = 1 );


namespace LutekDev\LutekSoloLeveling\task;

use LutekDev\LutekSoloLeveling\Main;
use pocketmine\scheduler\Task;

class SystemTask extends Task {
    public function __construct ( readonly private Main $plugin ) {}
    
    public function onRun () : void {
        foreach ($this->plugin->getServer()->getOnlinePlayers() as $player) {
            $session = $this->plugin->getSession( $player );
            
            if ($session !== null) {
                $session->update();
                
                $player->sendTip( "§cHP: " . $player->getHealth() );
            }
        }
    }
}