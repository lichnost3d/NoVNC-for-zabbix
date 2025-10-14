<?php
// novnc_cleanup.php

class NoVNCCleanup {
    private $session_timeout = 3600; // 1 час
    
    public function cleanupOldSessions() {
        $temp_dir = sys_get_temp_dir();
        $pattern = $temp_dir . "/novnc_*.json";
        
        $files = glob($pattern);
        $cleaned = 0;
        
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                unlink($file);
                $cleaned++;
                continue;
            }
            
            // Проверяем время создания сессии
            if (time() - $data['started'] > $this->session_timeout) {
                // Останавливаем процесс
                if (isset($data['pid'])) {
                    exec("kill {$data['pid']} 2>/dev/null");
                }
                
                // Удаляем файл сессии
                unlink($file);
                $cleaned++;
            }
        }
        
        return $cleaned;
    }
}

// Запуск очистки через cron
if (php_sapi_name() === 'cli') {
    $cleanup = new NoVNCCleanup();
    $cleaned = $cleanup->cleanupOldSessions();
    echo "Cleaned $cleaned old noVNC sessions\n";
}
?>