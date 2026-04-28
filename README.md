# AABB Brasília - Sistema de Clube em Laravel 12

Sistema demo funcional para a AABB Brasília, com site público de clube, portal do associado, painel da equipe, CRUDs Filament, PostgreSQL local e dados fictícios consistentes com o fluxo da reunião.

## Acessos do demo

- Site público: `http://127.0.0.1:8000`
- Portal do associado: `associado@aabb.demo` / `aabb2026`
- Equipe/secretaria: `equipe@aabb.demo` / `aabb2026`
- Painel Filament: `http://127.0.0.1:8000/admin`

## Banco PostgreSQL local

Foi criado um cluster PostgreSQL dedicado ao projeto em `.local-pgsql`, sem depender da senha do serviço PostgreSQL principal do Windows.

- Host: `127.0.0.1`
- Porta: `55432`
- Banco: `aabb_brasilia`
- Usuário: `aabb_app`
- Senha: `aabb_demo_2026`

Use esses dados no DBeaver.

## Comandos úteis

```powershell
# Entrar na pasta
cd C:\Users\joaog\Downloads\omaiortestequejaseviuopensource

# Subir o PostgreSQL local do projeto, se ele não estiver rodando
& 'C:\Program Files\PostgreSQL\18\bin\pg_ctl.exe' -D .local-pgsql -l .local-pgsql\server.log start

# Rodar migrations e dados demo
php artisan migrate:fresh --seed

# Subir Laravel
php artisan serve --host=127.0.0.1 --port=8000

# Compilar assets
npm run build

# Rodar testes
php artisan test
```

## O que está funcionando

- Home pública com cara de clube AABB: estrutura, planos, comunicados, benefícios, adesão e login.
- Portal do associado: carteirinha, financeiro, pagamento simulado, reservas, convidados, convites e dependentes.
- Painel da equipe: indicadores, associados, financeiro, reservas, estoque, propostas e portaria.
- Filament em `/admin`: CRUDs para associados, planos, cobranças, reservas, espaços, propostas, produtos, comunicados, benefícios, itens de cobrança e acesso.
- Integrações de pagamento, Pix/QR, boleto, débito BRB e catraca estão simuladas para demo, com estrutura pronta para integração real.

## Observação sobre imagens

As imagens usadas no demo apontam para arquivos públicos do site oficial da AABB Brasília. Antes de produção, substitua por assets aprovados/oficiais do cliente.
