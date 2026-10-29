# 🔗 Integrações

## Necessária: pelo menos uma fonte de IA

O StudioLMS gera tudo com IA, então **uma destas duas precisa estar disponível**. Sem nenhuma, o assistente e a estilização mostram orientação no lugar dos formulários, e nada é gerado.

| Fonte | O que precisa |
|---|---|
| [`local_aihub`](https://github.com/jeanlucio/moodle-local_aihub) (o Hub de IA, um plugin do mesmo autor) | O plugin instalado e uma chave de API (a pessoal, ou a do site) |
| `core_ai` (o subsistema de IA do Moodle, que faz parte do Moodle) | Um provedor configurado e habilitado para geração de texto, em **Administração do site > IA** |

Basta uma. Se as duas existirem, o Hub de IA é consultado primeiro e o `core_ai` responde quando o hub está ausente ou falha. Veja [IA e Serviços de Terceiros](#ai).

## Integrações opcionais

Nenhuma destas é necessária. Todas são **suaves** (o plugin confere se o outro está lá), e não há `$plugin->dependencies` obrigatórias.

| Plugin | O que acrescenta quando presente |
|---|---|
| `tiny_studiolms` | Os blocos visuais ricos das páginas geradas e estilizadas, e o editor para ajustá-los. Sem ele, as páginas são HTML semântico limpo e a estilização não consegue reabrir os blocos |
| `filter_studiolms` | A marca `[QUIZINFO]` na introdução de um questionário gerado, para que as regras fiquem sempre em dia. Só é usada onde o filtro está instalado, com o widget ligado e ativo no curso |
| `block_playerhud` | O modo gamificado |
