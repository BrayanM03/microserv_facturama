<?php
class IssuerRegistry {
    private PDO $database;

    public function __construct() {
        require_once __DIR__ . '/env.php';
        cargarEnv();

        $databasePath = getenv('FACTURAMA_DB_PATH') ?: dirname(__DIR__) . '/facturama-private/issuers.sqlite';
        $databaseDirectory = dirname($databasePath);
        if (!is_dir($databaseDirectory) && !mkdir($databaseDirectory, 0700, true) && !is_dir($databaseDirectory)) {
            throw new RuntimeException('No se pudo crear el directorio privado de datos.');
        }

        $this->database = new PDO('sqlite:' . $databasePath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        $this->database->exec('PRAGMA foreign_keys = ON');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS issuer_assignments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                system_name TEXT NOT NULL,
                client_id TEXT NOT NULL,
                client_name TEXT NOT NULL,
                issuer_rfc TEXT NOT NULL,
                issuer_name TEXT NOT NULL,
                fiscal_regime TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(system_name, client_id, issuer_rfc)
            )'
        );
        $this->database->exec('CREATE INDEX IF NOT EXISTS idx_assignments_client ON issuer_assignments(system_name, client_id)');
        @chmod($databasePath, 0600);
    }

    public function listForClient(string $systemName, string $clientId): array {
        $query = $this->database->prepare(
            'SELECT id, client_id, client_name, issuer_rfc, issuer_name, fiscal_regime
             FROM issuer_assignments WHERE system_name = :system AND client_id = :client
             ORDER BY issuer_name, issuer_rfc'
        );
        $query->execute(['system' => $systemName, 'client' => $clientId]);
        return $query->fetchAll();
    }

    public function listAll(): array {
        return $this->database->query(
            'SELECT id, system_name, client_id, client_name, issuer_rfc, issuer_name, fiscal_regime, created_at, updated_at
             FROM issuer_assignments ORDER BY system_name, client_name, issuer_name'
        )->fetchAll();
    }

    public function findForClient(string $systemName, string $clientId, int $issuerId): ?array {
        $query = $this->database->prepare(
            'SELECT id, client_id, client_name, issuer_rfc, issuer_name, fiscal_regime
             FROM issuer_assignments WHERE system_name = :system AND client_id = :client AND id = :id'
        );
        $query->execute(['system' => $systemName, 'client' => $clientId, 'id' => $issuerId]);
        $assignment = $query->fetch();
        return $assignment ?: null;
    }

    public function save(string $systemName, string $clientId, string $clientName, string $issuerRfc, string $issuerName, string $fiscalRegime): void {
        $query = $this->database->prepare(
            'INSERT INTO issuer_assignments (system_name, client_id, client_name, issuer_rfc, issuer_name, fiscal_regime)
             VALUES (:system, :client, :client_name, :rfc, :issuer_name, :regime)
             ON CONFLICT(system_name, client_id, issuer_rfc) DO UPDATE SET
                client_name = excluded.client_name,
                issuer_name = excluded.issuer_name,
                fiscal_regime = excluded.fiscal_regime,
                updated_at = CURRENT_TIMESTAMP'
        );
        $query->execute([
            'system' => $systemName,
            'client' => $clientId,
            'client_name' => $clientName,
            'rfc' => $issuerRfc,
            'issuer_name' => $issuerName,
            'regime' => $fiscalRegime
        ]);
    }

    public function delete(int $issuerId): bool {
        $query = $this->database->prepare('DELETE FROM issuer_assignments WHERE id = :id');
        $query->execute(['id' => $issuerId]);
        return $query->rowCount() > 0;
    }

}