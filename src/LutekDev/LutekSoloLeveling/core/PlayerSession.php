<?php

declare( strict_types = 1 );

namespace LutekDev\LutekSoloLeveling\core;

use LutekDev\LutekSoloLeveling\database\Database;
use LutekDev\LutekSoloLeveling\Main;
use pocketmine\entity\Attribute;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\item\Axe;
use pocketmine\player\Player;
use pocketmine\utils\Config;

class PlayerSession {
    // --- Virtual Health ---
    private float $maxHp = 20.0;
    private float $currentHp = 20.0;
    
    
    // Stats Atuais (Voláteis)
    private float $currentMp = 0.0;
    private float $maxMp = 100.0;
    
    
    // Atributos (Persistentes)
    private int $vitality;
    private int $agility;
    private int $intelligence;
    private int $strength;
    private int $sense;
    private int $defense;
    private float $fatigue = 0.0;
    
    // XP e Level
    private int $level;
    private float $currentXp;
    private float $xpToNextLevel;
    
    private int $lastMovementUpdateTick = 0;
    
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
        $this->currentXp = (float) ( $data[ "xp" ] ?? 0 );
        
        // Recalcula APÓS carregar os dados
        $this->recalculateStats();
        
        // Sincroniza visualmente pela primeira vez
        $this->syncVisualHealth();
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
        $gameplay = $this->plugin->getConfig()->get( "gameplay" );
        
        // 1. VITALITY → Vida Real
        // O HP Visual do Minecraft ficará fixo em 20, nós controlamos isso matematicamente.
        $this->maxHp = 20 + ( $this->vitality * $scalers[ "hp_per_vitality" ] );
        if ($this->maxHp > $caps[ 'max_hp' ]) $this->maxHp = $caps[ 'max_hp' ]; // Respeita o CAP
        
        // Mantém a vida visual sempre em 20 para não bugar a tela
        $this->player->setMaxHealth( 20 );
        
        // 2. AGILITY → Velocidade de Movimento
        // O valor base do MC é 0,10. Cuidado para não deixar o player rápido demais.
        $baseSpeed = 0.10;
        $bonusSpeed = $this->agility * $scalers[ 'speed_per_agility' ];
        $rawSpeed = $baseSpeed + $bonusSpeed;
        
        // Fator de Penalidade:
        // Se fatigue for 0, penalty é 0.
        // Se fatigue for 100, penalty é max_penalty (ex: 0.6 ou 60%)
        $penaltyFactor = ( $this->fatigue / 100.0 ) * ( $gameplay[ 'fatigue_speed_penalty' ] ?? 0.5 );
        
        // Aplica a redução
        $finalSpeed = $rawSpeed * ( 1.0 - $penaltyFactor );
        
        // Aplica e verifica mudança (para economizar pacotes de rede)
        $attr = $this->player->getAttributeMap()->get( Attribute::MOVEMENT_SPEED );
        if (abs( $attr->getValue() - $finalSpeed ) > 0.001) {
            $attr->setValue( $finalSpeed );
        }
        
        // 3. MP (Intelligence)
        $this->maxMp = 50 + ( $this->intelligence * 5 );
        
        // 4. CURVA DE XP (Dificuldade aumenta por nível)
        $this->xpToNextLevel = 100 * $this->level;
    }
    
    /**
     * Lógica de Tique (Chamado a cada segundo pelo servidor)
     * Gerencia MP e Fadiga Realista
     */
    public function update () : void {
        // Regeneração ddo MP baseada em Inteligência
        if ($this->currentMp < $this->maxMp) {
            $regen = $this->intelligence * 0.2;
            $this->currentMp = min( $this->maxMp, $this->currentMp + $regen );
        }
        
        // Regen HP (Vitality)
        if ($this->currentHp < $this->maxHp) {
            $this->currentHp += ( $this->vitality * 0.1 );
        }
        
        // FADIGA REALISTA
        
        // Recupera fadiga se ficar parado
        if (!$this->player->isSprinting() && !$this->player->hasMovementUpdate()) {
            $this->reduceFatigue( 2.0 ); // Descansa rápido se parado
        } else if (!$this->player->isSprinting()) {
            $this->reduceFatigue( 0.5 ); // Descansa devagar se andando
        }
        
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
        
        $this->recalculateStats();
        $this->syncVisualHealth();
    }
    
    // --- API / Getters e Setters ---
    public function applyDamage ( float $amount ) : bool {
        // Cálculo de Esquiva (Sense)
        // Ex: 100 Sense = 10% de chance de esquiva
        $dodgeChance = min( 30, $this->sense * 0.1 );
        
        if (mt_rand( 0, 100 ) < $dodgeChance) {
            $this->player->sendTip( "§e* ESQUIVOU *" );
            return false;
        }
        
        // Redução de Dano (Defense)
        $defensePercent = min( 80, $this->defense * 0.2 ); // Cap 80%
        $damageTaken = $amount * ( 1 - ( $defensePercent / 100 ) );
        
        $this->currentHp -= $damageTaken;
        
        if ($this->currentHp <= 0) {
            $this->currentHp = 0;
            $this->syncVisualHealth();
            return true; // Morreu
        }
        
        $this->syncVisualHealth();
        return false;
    }
    
    // Sincroniza a vida Real (2000) com a Visual (20 corações)
    public function syncVisualHealth () : void {
        if ($this->maxHp <= 0) return;
        
        // Regra de três: (VidaAtual / VidaMaxima) * 20
        $visualHealth = ( $this->currentHp / $this->maxHp ) * 20;
        
        // Garante que não fique 0 se tiver 1 de vida real, e não passe de 20
        $visualHealth = max( 1, min( 20, $visualHealth ) );
        
        // Define no cliente sem disparar eventos de dano
        $this->player->setHealth( $visualHealth );
        
        // Action Bar (Substituto da ScoreBoard)
        $this->player->sendActionBarMessage( "§cHP: " . (int) $this->currentHp . " §bMP: " . (int) $this->currentMp . " §eLv." . $this->level );
    }
    
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
    
    public function addXp ( float $amount ) : void {
        $this->currentXp += $amount;
        
        if ($this->currentXp >= $this->xpToNextLevel) {
            $this->levelUp();
        }
    }
    
    private function levelUp () : void {
        $this->level++;
        $this->currentXp = 0;
        $this->player->sendMessage( "§a(!) §7SUBIU DE NÍVEL!\n\n§7Agora você é nível §6" . $this->level );
        $this->player->getWorld()->addSound( $this->player->getPosition(), new \pocketmine\world\sound\XpLevelUpSound( 30 ) );
        
        // Ganha pontos para distribuir (Ex: 3 pontos)
        // Isso precisaria ser salvo no DB: "stat_points"
        
        $this->recalculateStats();
        $this->currentHp = $this->maxHp; // Recupera vida ao upar
        $this->syncVisualHealth();
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
    
    /**
     * Adiciona fadiga sem recalcular status imediatamente.
     * Usado no PlayerMoveEvent para alta performance.
     */
    public function addFatiguePassive ( float $amount ) : void {
        // Resistência baseada em Vitality (opcional, para dar valor a VIT)
        // Quanto mais VIT, menos cansa. Ex: 100 VIT = 10% menos cansaço
        $resistance = min( 0.5, $this->vitality * 0.001 );
        $amount = $amount * ( 1.0 - $resistance );
        
        $this->fatigue = min( 100.0, $this->fatigue + $amount );
        
        // Só avisa o cliente visualmente (ActionBar) se mudou muito ou passou 1s
        // Deixamos o recálculo pesado da velocidade para o SystemTask (update)
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