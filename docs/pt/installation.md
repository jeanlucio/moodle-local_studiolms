# 🛠️ Instalação

1. Baixe o arquivo `.zip` ou clone o repositório.
2. Extraia a pasta no diretório `local/` do seu Moodle e dê a ela o nome `studiolms`.
   Caminho final: `seu-moodle/local/studiolms/`
3. Acesse **Administração do site > Notificações** para concluir a instalação.
4. Os papéis que devem usar o plugin precisam de duas capacidades, concedidas por padrão a professores com edição e gerentes:
   * `local/studiolms:generate` para o assistente de geração;
   * `local/studiolms:restyle` para estilizar uma página ou rótulo que já existe.
5. O item **StudioLMS** aparece na navegação do curso, e **Estilizar com o StudioLMS** nas configurações de uma página, para quem tem essas capacidades.

Criar conteúdo também exige as capacidades do próprio Moodle para isso (`moodle/course:manageactivities` e `mod/<tipo>:addinstance` para cada tipo), e estilizar exige as capacidades de backup e restauração da atividade. O StudioLMS nunca concede mais do que o Moodle concederia.

A página de configurações (**Administração do site > Plugins > Plugins locais > StudioLMS**) só explica de onde vem a IA. Não há chave para informar.
