# AABB Brasilia - Sistema de Clube em Laravel 12

Sistema demo funcional para a AABB Brasilia, com site publico de clube, portal do associado, painel unico da equipe, PostgreSQL local e dados ficticios consistentes com o fluxo da reuniao.

## Acessos do demo

- Site publico: `http://127.0.0.1:8000`
- Portal do associado: `http://127.0.0.1:8000/portal`
- Painel da equipe: `http://127.0.0.1:8000/equipe`
- Redirecionamentos legados: `/admin` e `/gestao` apontam para `/equipe`
- Portal do associado: `associado@aabb.demo` / `aabb2026`
- Equipe geral: `equipe@aabb.demo` / `aabb2026`
- Financeiro: `financeiro@aabb.demo` / `aabb2026`
- Secretaria: `secretaria@aabb.demo` / `aabb2026`
- Portaria: `portaria@aabb.demo` / `aabb2026`

## Banco PostgreSQL local

Foi criado um cluster PostgreSQL dedicado ao projeto em `.local-pgsql`, sem depender da senha do servico PostgreSQL principal do Windows.

- Host: `127.0.0.1`
- Porta: `55432`
- Banco: `aabb_brasilia`
- Usuario: `aabb_app`
- Senha: `aabb_demo_2026`

Use esses dados no DBeaver.

## Comandos uteis

```powershell
cd C:\Users\joaog\Downloads\omaiortestequejaseviuopensource

& 'C:\Program Files\PostgreSQL\18\bin\pg_ctl.exe' -D .local-pgsql -l .local-pgsql\server.log start

php artisan migrate --seed
php artisan serve --host=127.0.0.1 --port=8000
npm run build
php artisan test
```

## O que esta funcionando

- Home publica com cara de clube AABB: estrutura, planos, comunicados, beneficios, adesao e login.
- Adesao direta pelo site: cria associado pendente, usuario de portal e primeira mensalidade; acesso libera apos pagamento confirmado.
- Portal do associado: carteirinha digital com flip, QR Code real, financeiro, comprovantes, reservas, convidados, convites e dependentes.
- Painel unico da equipe em `/equipe`: abas internas para Visao geral, Secretaria, Financeiro, Reservas e Convites, Portaria, Estoque e Conteudo.
- Validacao de carteirinha: QR aponta para `/carteirinha/validar/{token}`, protegido por login interno da equipe/portaria.
- Financeiro real sem gateway: geracao recorrente, cobranca avulsa/reserva/convite, status, baixa manual e recibo interno.
- Reservas reais: calendario visual no portal/equipe, endpoint de disponibilidade, bloqueio de conflito por espaco/data, cobranca vinculada e confirmacao automatica apos baixa.
- Convites reais: cota mensal por plano, excedente com cobranca, codigo de acesso e validacao pela portaria sem recarregar a pagina.
- Secretaria: importacao CSV/XLSX de associados e dependentes, propostas com aprovacao/conversao e documentos.
- Estoque profissional: SKU, QR Code por produto, ficha interna protegida, entrada/saida/ajuste/perda, saldo anterior/final, custo unitario, valor total, usuario responsavel, alertas e historico auditavel.

## Limitacoes assumidas

- BRB, boleto, QR de pagamento, gateway e catraca ainda sao simulacoes navegaveis sem credenciais reais.
- O QR da carteirinha e o QR do estoque nao expoem dados publicamente; eles abrem validacoes internas protegidas por login.
- Filament continua instalado como painel tecnico em rota nao divulgada, mas nao faz parte do fluxo do funcionario.
- Imagens do demo apontam para arquivos publicos do site oficial da AABB Brasilia e devem ser substituidas por assets aprovados antes da producao.
