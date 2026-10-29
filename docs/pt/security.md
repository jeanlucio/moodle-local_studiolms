# 🔐 Segurança e Conformidade

* **O acesso é sempre conferido no curso:** `require_login($course)` e `require_capability('local/studiolms:generate', ...)` no contexto do curso, conferidos de novo em cada serviço web e de novo dentro da tarefa em segundo plano, já que uma permissão pode ser revogada entre o clique e o cron.
* **Subordinado às capacidades do próprio Moodle:** criar conteúdo também exige `moodle/course:manageactivities`, `moodle/course:update` (para seções novas) e `mod/<tipo>:addinstance`; estilizar exige as capacidades de backup, restauração e adição da atividade. As capacidades do plugin nunca concedem mais que essas.
* **Sem criação de curso:** o assistente trabalha num curso existente, o que elimina a superfície do `course:create`.
* **Ações destrutivas são protegidas:** o apagar opcional exige `moodle/course:manageactivities` e uma confirmação explícita, preserva o fórum de Avisos, e um roteiro sem nada para construir é recusado antes de apagar qualquer coisa. A estilização nunca escreve no original.
* **Os registros são escopados por dono e curso:** uma linha de progresso só é lida por quem é dono dela, e um roteiro só quando o id, o usuário e o curso coincidem.
* **A saída da IA é entrada não confiável:** o JSON da IA é conferido contra o formato esperado, o texto passa por `clean_param()` e o HTML por `clean_text()`, e no modo de polir os links só são aceitos se o original os tinha, scripts são removidos, e imagens, tabelas e vídeos nunca vêm da IA.
* **Serviços web:** declarados em `db/services.php`, chamados pela camada AJAX do Moodle (que confere a chave de sessão e o contexto). Uma referência a uma seção é sempre procurada dentro do curso, nunca só pelo id.
* **O interruptor de IA do curso** é respeitado em cada ponto de entrada.
* **API de Privacidade:** exportação e exclusão das três tabelas com dados de um professor, e as linhas são removidas quando o curso é excluído.
* **Auditado:** o plugin foi auditado em 29 de outubro de 2026 e não teve achado de segurança; os achados de qualidade de código foram corrigidos.
