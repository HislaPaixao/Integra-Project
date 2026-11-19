<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * AghuService
 *
 * Responsável pela integração com a API externa do AGHU (sistema hospitalar).
 * Consome o endpoint de pacientes e retorna os dados para o PacienteController.
 *
 * Endpoint AGHU consumido:
 *   GET {AGHU_BASE_URL}/pacientes/{prontuario}
 *   Header: Authorization: Bearer {AGHU_TOKEN}
 *
 * Modo simulado (AGHU_MOCK=true no .env):
 *   Em vez de chamar o AGHU real, usa os pacientes de demonstração do arquivo
 *   database/data/aghu_pacientes.json. Serve para desenvolver e demonstrar o
 *   módulo sem acesso ao sistema do hospital.
 *
 * Casos de Borda tratados:
 *   EC3 — Paciente sem prontuário no AGHU → retorna null (fallback manual)
 *   EC6 — AGHU indisponível              → retorna null (fallback manual + log de falha)
 */
class AghuService
{
    private bool   $mock;
    private string $baseUrl;
    private string $token;
    private int    $timeout;

    public function __construct()
    {
        $this->mock    = (bool) config('services.aghu.mock', false);
        $this->baseUrl = (string) config('services.aghu.base_url', '');
        $this->token   = (string) config('services.aghu.token', '');
        $this->timeout = (int) config('services.aghu.timeout', 5);
    }

    /**
     * Busca os dados do paciente pelo número de prontuário.
     *
     * @param  string $prontuario  Número do prontuário do paciente
     * @return array|null          Dados do paciente ou null se não encontrado / AGHU indisponível
     */
    public function buscarPaciente(string $prontuario): ?array
    {
        $prontuario = trim($prontuario);

        if ($this->mock) {
            return $this->buscarNoMock($prontuario);
        }

        try {
            $response = Http::withToken($this->token)
                ->timeout($this->timeout)
                ->get("{$this->baseUrl}/pacientes/{$prontuario}");

            // Paciente não encontrado no AGHU (EC3)
            if ($response->status() === 404) {
                Log::info('[AghuService] Paciente não encontrado no AGHU.', [
                    'prontuario' => $prontuario,
                ]);
                return null;
            }

            // Outro erro HTTP
            if ($response->failed()) {
                Log::warning('[AghuService] Erro ao consultar AGHU.', [
                    'prontuario' => $prontuario,
                    'status'     => $response->status(),
                ]);
                return null;
            }

            return $this->mapear((array) $response->json(), $prontuario);

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            // AGHU fora do ar ou timeout (EC6)
            Log::error('[AghuService] AGHU indisponível — timeout ou conexão recusada.', [
                'prontuario' => $prontuario,
                'erro'       => $e->getMessage(),
            ]);
            return null;

        } catch (\Throwable $e) {
            // Qualquer outro erro inesperado
            Log::error('[AghuService] Erro inesperado ao consultar AGHU.', [
                'prontuario' => $prontuario,
                'erro'       => $e->getMessage(),
            ]);
            return null;
        }
    }

    /** Procura o prontuário na lista de pacientes de demonstração. */
    private function buscarNoMock(string $prontuario): ?array
    {
        $arquivo = database_path('data/aghu_pacientes.json');

        if (!is_file($arquivo)) {
            Log::warning('[AghuService] Arquivo de pacientes de demonstração não encontrado.', [
                'arquivo' => $arquivo,
            ]);
            return null;
        }

        $pacientes = json_decode((string) file_get_contents($arquivo), true) ?: [];

        if (!isset($pacientes[$prontuario])) {
            return null;
        }

        return $this->mapear($pacientes[$prontuario], $prontuario);
    }

    /** Converte os campos do AGHU para o contrato da nossa API. */
    private function mapear(array $dados, string $prontuario): array
    {
        return [
            'prontuario'     => (string) ($dados['nr_prontuario']    ?? $prontuario),
            'nomePaciente'   => $dados['nm_paciente']       ?? null,
            'cartaoSus'      => $dados['nr_cartao_sus']     ?? null,
            'cpf'            => $dados['nr_cpf']            ?? null,
            'leito'          => $dados['ds_leito']          ?? null,
            'numeroConsulta' => $dados['nr_consulta']       ?? null,
            'clinicaOrigem'  => $dados['ds_clinica_origem'] ?? null,
        ];
    }
}
