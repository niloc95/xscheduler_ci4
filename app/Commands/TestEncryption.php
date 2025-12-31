<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class TestEncryption extends BaseCommand
{
    protected $group       = 'Testing';
    protected $name        = 'test:encryption';
    protected $description = 'Test encryption and email integration save';

    public function run(array $params)
    {
        CLI::write('=== Testing Encryption ===', 'yellow');

        try {
            $encrypter = service('encrypter');
            
            $testData = json_encode([
                'host' => 'smtp.gmail.com',
                'port' => 587,
                'password' => 'testpassword123',
            ]);
            
            CLI::write("Original data: $testData");
            CLI::write("Original length: " . strlen($testData));
            
            $encrypted = $encrypter->encrypt($testData);
            CLI::write("Encrypted length: " . strlen($encrypted));
            CLI::write("Encrypted (first 50 chars): " . substr(base64_encode($encrypted), 0, 50) . "...");
            
            $decrypted = $encrypter->decrypt($encrypted);
            CLI::write("Decrypted: $decrypted");
            
            if ($decrypted === $testData) {
                CLI::write("\n✅ Encryption/Decryption PASSED!", 'green');
            } else {
                CLI::write("\n❌ Mismatch!", 'red');
            }
        } catch (\Throwable $e) {
            CLI::write("\n❌ Encryption ERROR: " . $e->getMessage(), 'red');
            CLI::write("File: " . $e->getFile() . ":" . $e->getLine());
        }

        CLI::write("\n=== Testing Email Integration Save ===", 'yellow');

        try {
            $emailSvc = new \App\Services\NotificationEmailService();
            
            $result = $emailSvc->saveIntegration(1, [
                'provider_name' => 'Test Gmail',
                'is_active' => true,
                'host' => 'smtp.gmail.com',
                'port' => 587,
                'crypto' => 'tls',
                'username' => 'test@gmail.com',
                'password' => 'testpassword123',
                'from_email' => 'test@gmail.com',
                'from_name' => 'WebSchedulr Test',
            ]);
            
            CLI::write("Save result: " . json_encode($result));
            
            // Check what was saved
            $db = \Config\Database::connect();
            $row = $db->table('xs_business_integrations')
                ->where('business_id', 1)
                ->where('channel', 'email')
                ->get()
                ->getRowArray();
            
            CLI::write("\nDatabase row after service save:");
            CLI::write("- ID: " . ($row['id'] ?? 'null'));
            CLI::write("- Provider: " . ($row['provider_name'] ?? 'null'));
            CLI::write("- Active: " . ($row['is_active'] ?? 'null'));
            CLI::write("- Config length: " . strlen($row['encrypted_config'] ?? ''));
            
            if (strlen($row['encrypted_config'] ?? '') > 0) {
                CLI::write("\n✅ Config was saved!", 'green');
                
                // Try to decrypt
                $public = $emailSvc->getPublicIntegration(1);
                CLI::write("\nDecrypted config:");
                CLI::write(print_r($public, true));
                
                if (!empty($public['config']['host'])) {
                    CLI::write("\n✅ Decryption successful - host: " . $public['config']['host'], 'green');
                } else {
                    CLI::write("\n❌ Decryption failed - host is empty", 'red');
                }
            } else {
                CLI::write("\n❌ Config is empty!", 'red');
            }
            
            if (strlen($row['encrypted_config'] ?? '') > 0) {
                CLI::write("\n✅ Config was saved!", 'green');
                
                // Try to decrypt
                $public = $emailSvc->getPublicIntegration(1);
                CLI::write("\nDecrypted config:");
                CLI::write(print_r($public, true));
            } else {
                CLI::write("\n❌ Config is empty!", 'red');
            }
            
        } catch (\Throwable $e) {
            CLI::write("\n❌ Save ERROR: " . $e->getMessage(), 'red');
            CLI::write("File: " . $e->getFile() . ":" . $e->getLine());
            CLI::write("Trace:\n" . $e->getTraceAsString());
        }
    }
}
