<?php

declare( strict_types = 1 );

namespace LutekDev\LutekSoloLeveling\core;

use LutekDev\LutekSoloLeveling\database\Database;
use LutekDev\LutekSoloLeveling\Main;
use pocketmine\entity\Attribute;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\player\Player;
use pocketmine\utils\Config;

class PlayerSession {
    // Stats Atuais (Voláteis)
    private float $currentMp = 0.0;
    private float $maxMp = 100.0;
    private float $fatigue = 0.0;
    
    
    // Atributos (Persistentes)
    private int $vitality;
    private int $agility;
    private int $intelligence;
    private int $strength;
    private int $sense;
    private int $defense;
    private int $level;
    
    public function __construct ( readonly private Player $player, readonly private Main $plugin ) {
        // Carrega do Banco de Dados
        $data = Database::getInstance()->loadPlayer( $this->player );
        $defaults = $this->plugin->getConfig()->get( "defaults" );
        
        $this->vitality = $data[ 'vitality' ] ?? $defaults[ 'vitality' ];
        $this->agility = $data[ 'agility' ] ?? $defaults[ 'agility' ];
        $this->intelligence = $data[ 'intelligence' ] ?? $defaults[ 'intelligence' ];
        $this->strength = $data[ 'strength' ] ?? $defaults[ 'strength' ];
        $this->sense = $data[ 'sense' ] ?? $defaults[ 'sense' ];
        $this->defense = $data[ 'defense' ] ?? $defaults[ 'defense' ];
        $this->level = $data[ 'level' ] ?? 1;
        
        $this->recalculateStats();
        
        // Nasce com mana cheia
        $this->currentMp = $this->maxMp;
    }
    
    public function getPlayer () : Player {
        return $this->player;
    }
    
    public function save () : void {
        Database::getInstance()->savePlayer( $this->player, [
            'vitality'     => $this->vitality,
            'agility'      => $this->agility,
            'intelligence' => $this->intelligence,
            'strength'     => $this->strength,
            'sense'        => $this->sense,
            'defense'      => $this->defense,
            'level'        => $this->level
        ] );
    }
    
    public function recalculateStats () : void {
        $scalers = $this->plugin->getConfig()->get( "scalers" );
        $caps = $this->plugin->getConfig()->get( "caps" );
        
        // 1. VITALITY → Vida Real
        $maxHealth = 20 + ( $this->vitality * $scalers[ "hp_per_vitality" ] );
        $newMaxHealth = min( (int) $maxHealth, $caps[ "max_hp" ] ); // Respeita o CAP
        $this->player->setMaxHealth( intval($maxHealth) );
        
        // Cura proporcional se aumentar a vida máxima para não ficar vazio
        if ($this->player->getHealth() > $newMaxHealth) {
            $this->player->setHealth( $newMaxHealth );
        }
        
        // 2. AGILITY → Velocidade de Movimento
        // O valor base do MC é 0,10. Cuidado para não deixar o player rápido demais.
        $baseSpeed = 0.10;
        $bonusSpeed = $this->agility * $scalers[ 'speed_per_agility' ];
        $finalSpeed = min( $baseSpeed + $bonusSpeed, $caps[ "max_speed" ] );
        
        $attr = $this->player->getAttributeMap()->get( Attribute::MOVEMENT_SPEED );
        if (abs( $attr->getValue() - $finalSpeed ) > 0.001) {
            $attr->setValue( $finalSpeed );
        }
        
        // 3. MP (Intelligence)
        $this->maxMp = $this->intelligence * 10;
    }
    
    /**
     * Lógica de Tique (Chamado a cada segundo pelo servidor)
     * Gerencia MP e Fadiga Realista
     */
    public function update () : void {
        // Regeneração ddo MP baseada em Inteligência
        if ($this->currentMp < $this->maxMp) {
            $regen = $this->intelligence * 0.1;
            $this->currentMp = min( $this->maxMp, $this->currentMp + $regen );
        }
        
        // FADIGA REALISTA
        // Se a fadiga passar de 70, o jogador começa a sofrer
        if ($this->fatigue > 70) {
            // Aplica lentidão (Slowness) visual e física
            $this->player->getEffects()->add( new EffectInstance( VanillaEffects::SLOWNESS(), 40, 1, false ) );
            
            // Se passar de 90, fadiga de mineração (braço cansado)
            if ($this->fatigue > 90) {
                $this->player->getEffects()->add( new EffectInstance( VanillaEffects::MINING_FATIGUE(), 40, 2, false ) );
                $this->player->sendActionBarMessage( "§cESTOU EXAUSTO..." );
            }
        }
        
        ScoreboardManager::updateBoard( $this->player, $this );
    }
    
    // --- API / Getters e Setters ---
    public function getAttackDamage () : float {
        $scaler = $this->plugin->getConfig()->get( "scalers" )[ 'damage_per_strength' ];
        return 1.0 + ( $this->strength * $scaler );
    }
    
    public function getDefenseFactor () : float {
        $scaler = $this->plugin->getConfig()->get( "scalers" )[ "defense_percentage" ];
        return min( $this->defense * $scaler, $this->plugin->getConfig()->get( "caps" )[ 'max_defense_percent' ] );
    }
    
    // Facilita para comandos de teste
    public function setAttribute ( string $attr, int $value ) : void {
        switch ($attr) {
            case 'str':
                $this->strength = $value;
            break;
            case 'vit':
                $this->vitality = $value;
            break;
            case 'agi':
                $this->agility = $value;
            break;
            case 'int':
                $this->intelligence = $value;
            break;
            case 'def':
                $this->defense = $value;
            break;
            case 'sen':
                $this->sense = $value;
            break;
        }
        $this->recalculateStats();
    }
    
    public function getHp () : float { return $this->player->getHealth(); }
    
    public function getMaxHp () : float { return $this->player->getMaxHealth(); }
    
    public function getMp () : float { return $this->currentMp; }
    
    public function getMaxMp () : float { return $this->maxMp; }
    
    public function getLevel () : int { return $this->level; }
    
    // Métodos para aumentar fadiga (ex. ao atacar ou correr)
    public function addFatigue ( float $amount ) : void {
        $this->fatigue = min( 100.0, $this->fatigue + $amount );
        $this->recalculateStats(); // Atualiza se necessário
    }
    
    public function reduceFatigue ( float $amount ) : void {
        $this->fatigue = max( 0.0, $this->fatigue - $amount );
    }
    
    // Métodos para danos críticos
    
    /**
     * Retorna a chance de crítico em porcentagem (0 a 100) baseada em SENSE.
     * Exemplo: 10 Sense = 2% chance. 100 Sense = 20% chance.
     */
    public function getCritChance () : float {
        // Fórmula: Cada ponto de Sense dá 0.2% de chance de crítico.
        // Cap (Limite): Máximos 50% de chance para não quebrar o jogo.
        return min( 50.0, $this->sense * 0.2 );
    }
    
    // Métodos para MP
    
    /**
     * Consome MP. Retorna true se tiver mana suficiente.
     */
    public function consumeMp ( float $amount ) : bool {
        if ($this->currentMp >= $amount) {
            $this->currentMp -= $amount;
            return true;
        }
        return false;
    }
}