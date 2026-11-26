import { TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import {
  provideHttpClientTesting,
  HttpTestingController
} from '@angular/common/http/testing';
import { ApiService, mensagemDeErro } from './api.service';

describe('ApiService', () => {

  let service: ApiService;
  let httpMock: HttpTestingController;

  // Prepara o ambiente antes de cada teste.
  beforeEach(() => {

    TestBed.configureTestingModule({
      providers: [
        ApiService,
        provideHttpClient(),
        provideHttpClientTesting()
      ]
    });

    service = TestBed.inject(ApiService);
    httpMock = TestBed.inject(HttpTestingController);

  });

  // Verifica se nenhuma requisicao ficou pendente.
  afterEach(() => {
    httpMock.verify();
  });

  // Teste 1: verifica a busca de paciente pelo prontuario.
  it('deve buscar paciente e remover espacos do prontuario', () => {

    const paciente = {
      prontuario: '12345',
      nomePaciente: 'Paciente Teste'
    };

    service.buscarPaciente('  12345  ').subscribe(resultado => {
      expect(resultado.prontuario).toBe('12345');
    });

    // Verifica se os espacos foram removidos da URL.
    const req = httpMock.expectOne('/api/pacientes/12345');

    // Verifica se o metodo HTTP utilizado foi GET.
    expect(req.request.method).toBe('GET');

    // Simula a resposta do servidor.
    req.flush(paciente);

  });

  // Teste 2: verifica a aplicacao dos filtros de solicitacoes.
  it('deve listar solicitacoes com filtros validos', () => {

    service.listar({
      status: 'Triado',
      prioridade: 'P1',
      busca: ''
    }).subscribe(resultado => {
      expect(resultado).toEqual([]);
    });

    // Intercepta a requisicao de listagem.
    const req = httpMock.expectOne(
      requisicao => requisicao.url === '/api/solicitacoes'
    );

    expect(req.request.method).toBe('GET');

    // Verifica se os filtros foram enviados corretamente.
    expect(req.request.params.get('status')).toBe('Triado');
    expect(req.request.params.get('prioridade')).toBe('P1');

    // Verifica se o filtro vazio foi ignorado.
    expect(req.request.params.has('busca')).toBeFalse();

    req.flush([]);

  });

  // Teste 3: verifica a criacao de uma solicitacao.
  it('deve criar uma solicitacao com os dados corretos', () => {

    const dados = {
      id_paciente: 1,
      especialidade: 'Cardiologia',
      descricao_clinica: 'Paciente necessita de acompanhamento especializado.'
    };

    const resposta = {
      id: 10,
      status: 'Aguarda triagem'
    };

    service.criar(dados).subscribe(resultado => {
      expect(resultado.id).toBe(10);
      expect(resultado.status).toBe('Aguarda triagem');
    });

    const req = httpMock.expectOne('/api/solicitacoes');

    // Verifica o metodo HTTP e os dados enviados.
    expect(req.request.method).toBe('POST');
    expect(req.request.body).toEqual(dados);

    // Simula a criacao bem-sucedida da solicitacao.
    req.flush(resposta, {
      status: 201,
      statusText: 'Created'
    });

  });

  // Teste 4: verifica o tratamento de mensagens de erro.
  it('deve retornar mensagens de erro corretamente', () => {

    // Simula um erro de validacao.
    const erroValidacao = {
      status: 422,
      error: {
        errors: {
          descricao_clinica: ['Descricao invalida.']
        }
      }
    };

    expect(mensagemDeErro(erroValidacao))
      .toBe('Descricao invalida.');

    // Simula um erro de permissao.
    const erroPermissao = {
      status: 403,
      error: {
        message: 'Acesso negado.'
      }
    };

    expect(mensagemDeErro(erroPermissao))
      .toBe('Acesso negado.');

    // Verifica a mensagem utilizada quando nao existe um erro especifico.
    expect(mensagemDeErro({}, 'Erro desconhecido.'))
      .toBe('Erro desconhecido.');

    // Simula uma falha de conexao com o servidor.
    expect(mensagemDeErro({ status: 0 }))
      .toContain('Sem conex');

  });

});
