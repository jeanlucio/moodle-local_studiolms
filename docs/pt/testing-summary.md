# 🧪 Testes Automatizados

* **PHPUnit:** **314 testes**, rodando no CI nos Moodles 4.5, 5.0, 5.1, 5.2 e 5.3. A cobertura de linhas da lógica é de **98%** (3.522 de 3.584 linhas, medida em 29 de outubro de 2026 com Xdebug). O que sobra descoberto é, em sua maior parte, código que só roda em uma versão do Moodle, e o gerador do PlayerHUD, que não tem ponto de injeção para teste.
* **Behat:** três features (acesso ao assistente, página de configurações, e a lista de estilização, a aba da página, a estilização em si e o modo de polir), rodando no CI com uma resposta de IA pronta que só existe enquanto o Behat roda.
* **Testes de regressão que falham sem a correção:** cada correção das auditorias de segurança vem com um teste que foi visto falhando antes, e as partes que protegem dados (o fórum de Avisos preservado por uma limpeza, o roteiro vazio recusado antes de apagar, o interruptor de IA em cada entrada) foram conferidas quebrando o código de propósito.
* **Os ganchos de teste são inertes em produção:** o provedor de IA determinístico e as respostas prontas só são lidos quando o PHPUnit ou o Behat está rodando.
