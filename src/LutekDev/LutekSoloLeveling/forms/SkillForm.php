<?php

declare(strict_types=1);

namespace LutekDev\LutekSoloLeveling\forms;

use LutekDev\LutekSoloLeveling\core\PlayerSession;
use pocketmine\form\Form;
use pocketmine\player\Player;

readonly class SkillForm implements Form {
    public function __construct(private PlayerSession $session) {}
    
    public function jsonSerialize(): array {
        return [
            "type" => "form",
            "title" => "§l§dSKILLS E STATUS",
            "content" => "§7Selecione uma ação:",
            "buttons" => [
                ["text" => "§l§eMEUS STATUS\n§r§7Ver atributos detalhados"], // Botão 0
                ["text" => "§l§bSKILL: DASH\n§r§7Custo: 20 MP"],          // Botão 1
                ["text" => "§l§cFECHAR"]                                    // Botão 2
            ]
        ];
    }
    
    public function handleResponse(Player $player, $data): void {
        if ($data === null) return;
        
        switch ($data) {
            case 0: // Status
                $player->sendMessage("§a--- Seus Status ---");
                // Aqui você pode abrir outro form com os detalhes
            break;
            
            case 1: // Skill Dash
                // Instancia e usa a skill (Como fizemos no exemplo anterior)
                $dash = new \LutekDev\LutekSoloLeveling\skills\list\DashSkill();
                $dash->cast($player, $this->session);
            break;
        }
    }
}