<?php

namespace App\Domain\Prd;

/**
 * Canonical PRD section definitions. Single source of truth for the
 * 21 required sections — key, title, guidance.
 */
final class SectionRegistry
{
    /** @var list<array{key: string, title: string, hint: string}> */
    public const SECTIONS = [
        ['key' => 'overview', 'title' => 'Overview', 'hint' => 'Ringkasan produk: apa, untuk siapa, kenapa.'],
        ['key' => 'problem', 'title' => 'Problem Statement', 'hint' => 'Masalah inti yang diselesaikan, bukti, dampaknya.'],
        ['key' => 'goals', 'title' => 'Goals', 'hint' => 'Tujuan produk & bisnis yang terukur.'],
        ['key' => 'target_users', 'title' => 'Target Users', 'hint' => 'Segmen pengguna utama + karakteristik.'],
        ['key' => 'user_personas', 'title' => 'User Personas', 'hint' => 'Persona konkret: nama, konteks, kebutuhan, pain points.'],
        ['key' => 'product_scope', 'title' => 'Product Scope', 'hint' => 'In-scope vs out-of-scope, batasan produk.'],
        ['key' => 'mvp_scope', 'title' => 'MVP Scope', 'hint' => 'Fitur yang masuk MVP + yang ditunda.'],
        ['key' => 'user_journey', 'title' => 'User Journey', 'hint' => 'Alur penggunaan end-to-end: trigger → langkah → outcome.'],
        ['key' => 'features', 'title' => 'Features', 'hint' => 'Fitur utama dengan prioritas.'],
        ['key' => 'functional_requirements', 'title' => 'Functional Requirements', 'hint' => 'Requirement fungsional bernomor (REQ-xxx).'],
        ['key' => 'non_functional_requirements', 'title' => 'Non-Functional Requirements', 'hint' => 'Performa, keamanan, skalabilitas, reliabilitas.'],
        ['key' => 'ux_requirements', 'title' => 'UX Requirements', 'hint' => 'Prinsip UX, key flows, state penting.'],
        ['key' => 'technical_requirements', 'title' => 'Technical Requirements', 'hint' => 'Arsitektur, stack, integrasi, constraint teknis.'],
        ['key' => 'data_requirements', 'title' => 'Data Requirements', 'hint' => 'Entitas data, relasi, retensi, privasi.'],
        ['key' => 'api_requirements', 'title' => 'API Requirements', 'hint' => 'Endpoint, kontrak, autentikasi, rate limit.'],
        ['key' => 'security_requirements', 'title' => 'Security Requirements', 'hint' => 'Auth, otorisasi, enkripsi, audit.'],
        ['key' => 'analytics', 'title' => 'Analytics', 'hint' => 'Metrik tracking event, funnel, dashboard.'],
        ['key' => 'risks', 'title' => 'Risks', 'hint' => 'Risiko produk/teknis/bisnis + mitigasi.'],
        ['key' => 'dependencies', 'title' => 'Dependencies', 'hint' => 'Pihak, layanan, atau keputusan eksternal yang dibutuhkan.'],
        ['key' => 'success_metrics', 'title' => 'Success Metrics', 'hint' => 'KPI konkret per timeline (launch, 30 hari, dst).'],
        ['key' => 'roadmap', 'title' => 'Roadmap', 'hint' => 'Fase rilis: MVP → v1 → v2.'],
    ];

    /** @return list<string> */
    public static function keys(): array
    {
        return array_column(self::SECTIONS, 'key');
    }

    public static function title(string $key): string
    {
        foreach (self::SECTIONS as $section) {
            if ($section['key'] === $key) {
                return $section['title'];
            }
        }

        return ucfirst(str_replace('_', ' ', $key));
    }

    public static function isKnown(string $key): bool
    {
        return in_array($key, self::keys(), true);
    }
}
