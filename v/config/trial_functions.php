<?php
/**
 * Funções auxiliares para gerenciamento de trials
 * trial_7d: Acesso completo por 7 dias
 * trial_24h: Acesso a 3 palestras por 24 horas
 */

/**
 * Verifica se o trial do usuário expirou e atualiza o status se necessário
 * 
 * @param PDO $pdo Conexão com o banco
 * @param array $user Dados do usuário
 * @return array|null Dados atualizados do usuário ou null se não é trial
 */
function checkAndUpdateTrialStatus($pdo, $user) {
    if (!in_array($user['role'], ['trial_7d', 'trial_24h'])) {
        return null;
    }
    
    $now = new DateTime();
    $trialStarted = $user['trial_started_at'] ? new DateTime($user['trial_started_at']) : null;
    
    // Se o trial ainda não foi iniciado, não faz nada (será iniciado no primeiro acesso a conteúdo)
    if (!$trialStarted) {
        return $user;
    }
    
    $expired = false;
    $expirationReason = '';
    
    if ($user['role'] === 'trial_7d') {
        // Trial de 7 dias
        $expirationDate = clone $trialStarted;
        $expirationDate->modify('+7 days');
        
        if ($now > $expirationDate) {
            $expired = true;
            $expirationReason = 'Período de 7 dias expirado';
        }
    } elseif ($user['role'] === 'trial_24h') {
        // Trial de 24 horas
        $expirationDate = clone $trialStarted;
        $expirationDate->modify('+24 hours');
        
        if ($now > $expirationDate) {
            $expired = true;
            $expirationReason = 'Período de 24 horas expirado';
        }
        
        // Verificar também se já assistiu 3 palestras
        $lecturesWatched = json_decode($user['trial_lectures_watched'] ?? '[]', true);
        if (count($lecturesWatched) >= 3) {
            // Não expira automaticamente por palestras, apenas limita o acesso a novas
        }
    }
    
    if ($expired) {
        expireTrialUser($pdo, $user);
        $user['role'] = 'free';
        $user['expired'] = true;
        $user['expiration_reason'] = $expirationReason;
    }
    
    return $user;
}

/**
 * Expira o trial do usuário, movendo-o para free e adicionando à lista leads_fundo
 * 
 * @param PDO $pdo Conexão com o banco
 * @param array $user Dados do usuário
 */
function expireTrialUser($pdo, $user) {
    $trialType = $user['role'];
    $lecturesWatched = json_decode($user['trial_lectures_watched'] ?? '[]', true);
    
    try {
        $pdo->beginTransaction();
        
        // Atualizar usuário para free e marcar que já usou o trial
        $hadTrialField = $trialType === 'trial_7d' ? 'had_trial_7d' : 'had_trial_24h';
        
        $stmt = $pdo->prepare("
            UPDATE users 
            SET role = 'free', 
                {$hadTrialField} = 1,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$user['id']]);
        
        // Adicionar à tabela leads_fundo (se não existir)
        $stmt = $pdo->prepare("
            INSERT INTO leads_fundo (user_id, nome, email, trial_type, expired_at, lectures_watched)
            VALUES (?, ?, ?, ?, NOW(), ?)
            ON DUPLICATE KEY UPDATE 
                expired_at = NOW(),
                lectures_watched = VALUES(lectures_watched)
        ");
        $stmt->execute([
            $user['id'],
            $user['name'],
            $user['email'],
            $trialType,
            count($lecturesWatched)
        ]);
        
        $pdo->commit();
        
        // Log da expiração
        error_log("[Trial] Usuário {$user['email']} expirou do {$trialType}. Palestras assistidas: " . count($lecturesWatched));
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("[Trial] Erro ao expirar trial do usuário {$user['email']}: " . $e->getMessage());
    }
}

/**
 * Inicia o período de trial do usuário (chamado no primeiro login ou primeiro acesso a conteúdo)
 * 
 * @param PDO $pdo Conexão com o banco
 * @param string $userId ID do usuário
 */
function startTrialPeriod($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("
            UPDATE users 
            SET trial_started_at = NOW() 
            WHERE id = ? AND trial_started_at IS NULL
        ");
        $stmt->execute([$userId]);
        
        error_log("[Trial] Período de trial iniciado para usuário: {$userId}");
        
    } catch (Exception $e) {
        error_log("[Trial] Erro ao iniciar trial: " . $e->getMessage());
    }
}

/**
 * Registra uma palestra assistida pelo usuário trial_24h
 * 
 * @param PDO $pdo Conexão com o banco
 * @param string $userId ID do usuário
 * @param string $lectureId ID da palestra
 * @return array ['success' => bool, 'message' => string, 'remaining' => int]
 */
function registerTrialLectureWatch($pdo, $userId, $lectureId) {
    try {
        // Buscar dados atuais do usuário
        $stmt = $pdo->prepare("SELECT role, trial_lectures_watched, trial_started_at FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user || $user['role'] !== 'trial_24h') {
            return ['success' => false, 'message' => 'Usuário não é trial_24h', 'remaining' => 0];
        }
        
        // Iniciar trial se ainda não foi iniciado
        if (!$user['trial_started_at']) {
            startTrialPeriod($pdo, $userId);
        }
        
        $lecturesWatched = json_decode($user['trial_lectures_watched'] ?? '[]', true);
        
        // Verificar se já assistiu esta palestra (não conta novamente)
        if (in_array($lectureId, $lecturesWatched)) {
            return [
                'success' => true, 
                'message' => 'Palestra já assistida anteriormente', 
                'remaining' => 3 - count($lecturesWatched),
                'already_watched' => true
            ];
        }
        
        // Verificar se já atingiu o limite
        if (count($lecturesWatched) >= 3) {
            return [
                'success' => false, 
                'message' => 'Você já assistiu suas 3 palestras do período de degustação.', 
                'remaining' => 0
            ];
        }
        
        // Adicionar palestra à lista
        $lecturesWatched[] = $lectureId;
        
        $stmt = $pdo->prepare("UPDATE users SET trial_lectures_watched = ? WHERE id = ?");
        $stmt->execute([json_encode($lecturesWatched), $userId]);
        
        $remaining = 3 - count($lecturesWatched);
        
        return [
            'success' => true, 
            'message' => "Palestra registrada. Restam {$remaining} palestra(s).", 
            'remaining' => $remaining
        ];
        
    } catch (Exception $e) {
        error_log("[Trial] Erro ao registrar palestra: " . $e->getMessage());
        return ['success' => false, 'message' => 'Erro ao processar', 'remaining' => 0];
    }
}

/**
 * Verifica se o usuário trial_24h pode assistir uma nova palestra
 * 
 * @param PDO $pdo Conexão com o banco
 * @param string $userId ID do usuário
 * @param string $lectureId ID da palestra
 * @return array ['can_watch' => bool, 'message' => string, 'remaining' => int]
 */
function canTrialWatchLecture($pdo, $userId, $lectureId) {
    try {
        $stmt = $pdo->prepare("SELECT role, trial_lectures_watched, trial_started_at FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user || $user['role'] !== 'trial_24h') {
            return ['can_watch' => true, 'message' => '', 'remaining' => 0];
        }
        
        // Verificar se o período de 24h expirou
        if ($user['trial_started_at']) {
            $now = new DateTime();
            $trialStarted = new DateTime($user['trial_started_at']);
            $expirationDate = clone $trialStarted;
            $expirationDate->modify('+24 hours');
            
            if ($now > $expirationDate) {
                return [
                    'can_watch' => false, 
                    'message' => 'Seu período de 24 horas expirou.', 
                    'remaining' => 0,
                    'expired' => true
                ];
            }
        }
        
        $lecturesWatched = json_decode($user['trial_lectures_watched'] ?? '[]', true);
        
        // Se já assistiu esta palestra, pode assistir novamente
        if (in_array($lectureId, $lecturesWatched)) {
            return [
                'can_watch' => true, 
                'message' => 'Você já assistiu esta palestra.', 
                'remaining' => 3 - count($lecturesWatched),
                'already_watched' => true
            ];
        }
        
        // Verificar se ainda tem palestras disponíveis
        if (count($lecturesWatched) >= 3) {
            return [
                'can_watch' => false, 
                'message' => 'Você já assistiu suas 3 palestras do período de degustação. Para continuar assistindo, assine o plano completo.', 
                'remaining' => 0
            ];
        }
        
        return [
            'can_watch' => true, 
            'message' => '', 
            'remaining' => 3 - count($lecturesWatched)
        ];
        
    } catch (Exception $e) {
        error_log("[Trial] Erro ao verificar permissão: " . $e->getMessage());
        return ['can_watch' => false, 'message' => 'Erro ao verificar permissão', 'remaining' => 0];
    }
}

/**
 * Obtém informações do status do trial para exibição no perfil
 * 
 * @param PDO $pdo Conexão com o banco
 * @param array $user Dados do usuário
 * @return array Informações do trial
 */
function getTrialStatusInfo($pdo, $user) {
    if (!in_array($user['role'], ['trial_7d', 'trial_24h'])) {
        return null;
    }
    
    $now = new DateTime();
    $trialStarted = $user['trial_started_at'] ? new DateTime($user['trial_started_at']) : null;
    $lecturesWatched = json_decode($user['trial_lectures_watched'] ?? '[]', true);
    
    $info = [
        'type' => $user['role'],
        'type_label' => $user['role'] === 'trial_7d' ? 'Acesso de 7 Dias' : 'Degustação (3 Palestras)',
        'started' => $trialStarted ? true : false,
        'started_at' => $trialStarted ? $trialStarted->format('d/m/Y H:i') : null,
        'lectures_watched' => count($lecturesWatched),
        'lectures_remaining' => $user['role'] === 'trial_24h' ? max(0, 3 - count($lecturesWatched)) : null,
    ];
    
    if ($trialStarted) {
        if ($user['role'] === 'trial_7d') {
            $expirationDate = clone $trialStarted;
            $expirationDate->modify('+7 days');
            $interval = $now->diff($expirationDate);
            
            if ($now > $expirationDate) {
                $info['time_remaining'] = 'Expirado';
                $info['expired'] = true;
            } else {
                $days = $interval->days;
                $hours = $interval->h;
                $info['time_remaining'] = "{$days} dia(s) e {$hours} hora(s)";
                $info['expires_at'] = $expirationDate->format('d/m/Y H:i');
                $info['expired'] = false;
            }
        } else {
            $expirationDate = clone $trialStarted;
            $expirationDate->modify('+24 hours');
            $interval = $now->diff($expirationDate);
            
            if ($now > $expirationDate) {
                $info['time_remaining'] = 'Expirado';
                $info['expired'] = true;
            } else {
                $hours = $interval->h;
                $minutes = $interval->i;
                $info['time_remaining'] = "{$hours}h {$minutes}min";
                $info['expires_at'] = $expirationDate->format('d/m/Y H:i');
                $info['expired'] = false;
            }
        }
    } else {
        $info['time_remaining'] = 'Não iniciado';
        $info['message'] = 'O contador começará quando você acessar o primeiro conteúdo.';
    }
    
    return $info;
}

/**
 * Verifica se o usuário pode receber um novo trial
 * 
 * @param PDO $pdo Conexão com o banco
 * @param string $email Email do usuário
 * @param string $trialType Tipo do trial (trial_7d ou trial_24h)
 * @return array ['can_receive' => bool, 'message' => string]
 */
function canReceiveTrial($pdo, $email, $trialType) {
    try {
        $stmt = $pdo->prepare("SELECT had_trial_7d, had_trial_24h, role FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            // Novo usuário, pode receber
            return ['can_receive' => true, 'message' => ''];
        }
        
        // Verificar se já está em um trial ou é assinante
        if (in_array($user['role'], ['subscriber', 'admin', 'trial_7d', 'trial_24h'])) {
            return [
                'can_receive' => false, 
                'message' => 'Você já possui acesso à plataforma.'
            ];
        }
        
        // Verificar se já usou este tipo de trial
        $hadTrialField = $trialType === 'trial_7d' ? 'had_trial_7d' : 'had_trial_24h';
        if ($user[$hadTrialField]) {
            return [
                'can_receive' => false, 
                'message' => 'Você já utilizou este benefício anteriormente. Para continuar acessando, assine o plano completo.'
            ];
        }
        
        return ['can_receive' => true, 'message' => ''];
        
    } catch (Exception $e) {
        error_log("[Trial] Erro ao verificar elegibilidade: " . $e->getMessage());
        return ['can_receive' => false, 'message' => 'Erro ao verificar elegibilidade'];
    }
}

/**
 * Verifica se o usuário tem acesso à videoteca baseado no role
 * 
 * @param string $role Role do usuário
 * @return bool
 */
function hasVideotecaAccessByRole($role) {
    return in_array($role, ['admin', 'subscriber', 'trial_7d', 'trial_24h']);
}
