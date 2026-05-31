<?php

declare(strict_types=1);

namespace MyInvoice\Service\Invoice;

use MyInvoice\Infrastructure\Config\Config;
use MyInvoice\Infrastructure\Database\Connection;
use PDO;

/**
 * Resolver default hodnot pro novou fakturu — ze supplier, client, project.
 */
final class InvoiceDefaults
{
    public function __construct(
        private readonly Connection $db,
        private readonly Config $config,
    ) {}

    /**
     * Doplní chybějící pole v $data podle defaultů.
     */
    public function resolve(array $data): array
    {
        $pdo = $this->db->pdo();
        $today = date('Y-m-d');
        $tz = (string) $this->config->get('app.timezone', 'Europe/Prague');

        $clientId = (int) ($data['client_id'] ?? 0);
        $projectId = isset($data['project_id']) && $data['project_id'] ? (int) $data['project_id'] : null;

        $client = null;
        if ($clientId) {
            $stmt = $pdo->prepare('SELECT supplier_id, language, currency_default_id, reverse_charge, payment_due_default FROM clients WHERE id = ?');
            $stmt->execute([$clientId]);
            $client = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        $project = null;
        if ($projectId) {
            $stmt = $pdo->prepare('SELECT client_id, currency_id, payment_due_days, hourly_rate FROM projects WHERE id = ?');
            $stmt->execute([$projectId]);
            $project = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            // MS-P1-1: project musí patřit zadanému klientovi
            if ($project !== null && (int) $project['client_id'] !== $clientId) {
                throw new \InvalidArgumentException("Zakázka #$projectId nepatří klientovi #$clientId.");
            }
        }

        // Supplier defaults — bere z clientova supplier_id (invoice je vždy v rámci klientova supplier)
        $supplier = null;
        if ($client !== null && !empty($client['supplier_id'])) {
            $stmt = $pdo->prepare('SELECT default_currency_id, default_payment_due_days FROM supplier WHERE id = ?');
            $stmt->execute([(int) $client['supplier_id']]);
            $supplier = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        $supplierId = $client['supplier_id'] ?? 0;

        // Defaults
        $data['issue_date'] = (string) ($data['issue_date'] ?? $today);

        $type = (string) ($data['invoice_type'] ?? 'invoice');
        if ($type === 'proforma') {
            $data['tax_date'] = null;
        } else {
            $data['tax_date'] = (string) ($data['tax_date'] ?? $today);
        }

        if (empty($data['currency_id'])) {
            // Legacy: pokud frontend posílá `currency` (code), resolve na id (scope per supplier)
            if (!empty($data['currency']) && is_string($data['currency']) && $supplierId > 0) {
                $stmt = $pdo->prepare(
                    'SELECT id FROM currencies WHERE supplier_id = ? AND code = ? ORDER BY is_default DESC, id ASC LIMIT 1'
                );
                $stmt->execute([(int) $supplierId, strtoupper($data['currency'])]);
                $found = (int) $stmt->fetchColumn();
                if ($found > 0) $data['currency_id'] = $found;
            }
        }
        if (empty($data['currency_id'])) {
            $data['currency_id'] = (int) (
                $project['currency_id']
                ?? $client['currency_default_id']
                ?? $supplier['default_currency_id']
                ?? 0
            );
            if ($data['currency_id'] <= 0 && $supplierId > 0) {
                // Fallback: default tuzemská měna supplier — CZ profil 'CZK', SK profil 'EUR'
                // (country.local_currency). Drží konzistenci se StatementMatcher::$localCurrency.
                $localCurrency = strtoupper((string) $this->config->get('country.local_currency', 'CZK'));
                $stmt = $pdo->prepare(
                    'SELECT id FROM currencies WHERE supplier_id = ? AND code = ? ORDER BY is_default DESC LIMIT 1'
                );
                $stmt->execute([(int) $supplierId, $localCurrency]);
                $data['currency_id'] = (int) $stmt->fetchColumn();
            }
        }

        // MS-P1-2: ověř že currency_id patří klientovu supplier (cross-supplier integrity)
        if (!empty($data['currency_id']) && $supplierId > 0) {
            $check = $pdo->prepare('SELECT 1 FROM currencies WHERE id = ? AND supplier_id = ?');
            $check->execute([(int) $data['currency_id'], (int) $supplierId]);
            if (!$check->fetchColumn()) {
                throw new \InvalidArgumentException(
                    "Měna #{$data['currency_id']} nepatří supplier #{$supplierId} klienta."
                );
            }
        }

        if (empty($data['language'])) {
            $data['language'] = $client['language'] ?? 'cs';
        }

        if (!isset($data['reverse_charge'])) {
            $data['reverse_charge'] = (bool) ($client['reverse_charge'] ?? false);
        }

        if (empty($data['due_date'])) {
            $days = (int) (
                $project['payment_due_days']
                ?? $client['payment_due_default']
                ?? $supplier['default_payment_due_days']
                ?? 7
            );
            $data['due_date'] = date('Y-m-d', strtotime($data['issue_date'] . " +{$days} days"));
        }

        return $data;
    }
}
