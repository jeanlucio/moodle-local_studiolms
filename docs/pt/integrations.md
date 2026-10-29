# 🔗 Integrações Opcionais

O StudioLMS funciona sozinho no Moodle 4.5 ou superior. Toda integração é **suave** (o plugin confere se o outro está lá): não há `$plugin->dependencies` obrigatórias.

| Plugin | O que acrescenta quando presente |
|---|---|
| `tiny_studiolms` | Os blocos visuais ricos das páginas geradas e estilizadas, e o editor para ajustá-los. Sem ele, as páginas são HTML semântico limpo e a estilização não consegue reabrir os blocos |
| `filter_studiolms` | A marca `[QUIZINFO]` na introdução de um questionário gerado, para que as regras fiquem sempre em dia. Só é usada onde o filtro está instalado, com o widget ligado e ativo no curso |
| `block_playerhud` | O modo gamificado |
| `local_aihub` | O Hub de IA: chaves do usuário ou do site |
| `core_ai` (Moodle) | Os provedores de IA configurados no site |
