# 🤖 IA e Serviços de Terceiros

O StudioLMS **não tem chave nem tela de chave próprias**. A geração de texto é resolvida nesta ordem, cada passo opcional:

| Ordem | Fonte |
|-------|-------|
| 1 | **[`local_aihub`](https://github.com/jeanlucio/moodle-local_aihub)** (o Hub de IA, do mesmo autor): quando instalado e com uma chave (pessoal, depois do site) |
| 2 | **`core_ai` do Moodle**: chamado diretamente; não precisa de chave quando o site já tem um provedor |
| 3 | Uma mensagem clara pede ao administrador que configure a IA (Administração do site > IA), ou instale o hub |

As chamadas são agrupadas (uma por questionário, uma por roteiro) e uma resposta malformada é repetida com pausa entre as tentativas, para ser gentil com os planos gratuitos dos provedores.

## O interruptor de ferramentas de IA do curso

A partir do Moodle 5.0 cada curso tem um interruptor das ferramentas de IA. O StudioLMS o respeita **antes de qualquer fonte, o Hub de IA inclusive**: os cinco serviços web do assistente e a estilização recusam um curso que está com as ferramentas desligadas, as tarefas em segundo plano conferem de novo quando rodam (ele pode ser desligado entre o clique e o cron), e o assistente e a estilização mostram um aviso no lugar dos formulários. Antes do Moodle 5.0 não há interruptor, então nada é conferido.

## Divulgação de serviço de terceiros

A IA é essencial ao plugin, mas ele depende inteiramente da IA que o site já oferece.

* **O que sai do site:** o tema do professor, o material de referência colado e, na estilização, o texto da página ou rótulo. Vai para o provedor que o site resolveu (o Hub de IA ou o `core_ai`), sob os termos e a política de privacidade desse provedor.
* **O que é guardado:** o StudioLMS não guarda respostas brutas da IA. Ele guarda o que o professor digitou no assistente (tema e material colado) em tabelas próprias (rascunho de roteiro e registro de gerações), sem expiração automática. Elas são exportadas e apagadas pela API de Privacidade e removidas quando o curso é excluído. **Não cole dados pessoais no campo de referência.**
* **Quando:** nada é enviado a menos que uma geração ou uma estilização seja iniciada explicitamente.
* **A saída da IA não é confiável:** ela é validada e limpa antes de ser mostrada ou salva (veja [Segurança](#security)).
