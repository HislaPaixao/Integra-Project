<?php

namespace Database\Seeders;

use App\Models\Paciente;
use App\Models\Prioridade;
use App\Models\Solicitacao;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Dados de demonstração: 3 usuários (senha "senha123") + pacientes + solicitações
 * em todos os estados do fluxo, para a tela já abrir "viva".
 * Pode ser executado várias vezes (usa updateOrCreate / só cria solicitações se não houver).
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $senha = Hash::make('senha123');

        $medico = User::updateOrCreate(['email' => 'medico@hospital.gov.br'], [
            'name' => 'Dr. Carlos Mendes', 'password' => $senha, 'crm' => 'PR-54321', 'perfil' => User::PERFIL_SOLICITANTE,
        ]);
        $regulador = User::updateOrCreate(['email' => 'regulador@hospital.gov.br'], [
            'name' => 'Dra. Helena Souza', 'password' => $senha, 'crm' => 'DF-11111', 'perfil' => User::PERFIL_REGULADOR,
        ]);
        $nir = User::updateOrCreate(['email' => 'nir@hospital.gov.br'], [
            'name' => 'Marina Lopes (NIR)', 'password' => $senha, 'crm' => null, 'perfil' => User::PERFIL_NIR,
        ]);

        if (Solicitacao::exists()) {
            return;
        }

        $dados = json_decode(file_get_contents(database_path('data/aghu_pacientes.json')), true);
        $pacientes = [];
        foreach ($dados as $prontuario => $p) {
            $pacientes[$prontuario] = Paciente::updateOrCreate(['prontuario' => (string) $prontuario], [
                'nome' => $p['nm_paciente'], 'cpf' => isset($p['nr_cpf']) ? substr(preg_replace('/\D/', '', $p['nr_cpf']), 0, 11) : null,
                'cartao_sus' => $p['nr_cartao_sus'] ?? null, 'leito' => $p['ds_leito'] ?? null,
            ]);
        }

        $casos = [
            ['123456', 'Endocrinologia adulto', 'Paciente com diabetes tipo 2 descompensada, em uso de metformina 850mg 2x/dia.', Solicitacao::STATUS_AGUARDA_TRIAGEM, 3],
            ['654321', 'Gastroenterologia', 'Dor abdominal recorrente há 3 meses com perda de peso não intencional.', Solicitacao::STATUS_AGUARDA_TRIAGEM, 2],
            ['345678', 'Pneumologia', 'Tosse crônica há 8 semanas, tabagista, radiografia com opacidade em lobo superior.', Solicitacao::STATUS_PENDENTE, 5],
            ['778899', 'Urologia', 'Hematúria macroscópica episódica, homem de 62 anos, sem febre.', Solicitacao::STATUS_TRIADO, 6],
            ['889900', 'Cirurgia geral', 'Hérnia inguinal sintomática à direita, com limitação de esforço físico.', Solicitacao::STATUS_FINALIZADO, 9],
        ];

        foreach ($casos as [$pront, $esp, $desc, $status, $dias]) {
            $s = Solicitacao::create([
                'id_paciente' => $pacientes[$pront]->id_paciente, 'id_solicitante' => $medico->id,
                'especialidade' => $esp, 'descricao_clinica' => $desc, 'status' => 'Aguarda triagem',
                'data_solicitacao' => now()->subDays($dias),
            ]);
            $s->registrarHistorico($medico, 'CRIACAO', 'Solicitação criada.');

            if ($status === Solicitacao::STATUS_PENDENTE) {
                $s->registrarHistorico($regulador, 'PENDENCIA', 'Informar carga tabágica (maços/ano) e se há exame de imagem recente.');
            }
            if (in_array($status, [Solicitacao::STATUS_TRIADO, Solicitacao::STATUS_FINALIZADO], true)) {
                $prio = $status === Solicitacao::STATUS_TRIADO ? 'P1' : 'P3';
                Prioridade::create(['id_solicitacao' => $s->id_solicitacao, 'id_regulador' => $regulador->id,
                    'prioridade_final' => $prio, 'justificativa' => 'Triagem concluída.']);
                $s->pareceres()->create(['id_regulador' => $regulador->id, 'texto_parecer' => 'Caso com indicação de avaliação especializada.', 'data_parecer' => now()->subDays($dias - 1)]);
                $s->registrarHistorico($regulador, 'TRIAGEM', "Prioridade {$prio}. Caso com indicação de avaliação especializada.");
            }
            if ($status === Solicitacao::STATUS_FINALIZADO) {
                $s->agendamentos()->create(['data_agendamento' => now()->addDays(14), 'local' => 'Ambulatório HUB - sala 4', 'status' => 'Agendado']);
                $s->registrarHistorico($nir, 'REGULACAO', 'Agendado. Vaga disponível.');
            }
            $s->update(['status' => $status]);
        }
    }
}
