# ✨ Funcionalidades

* 🪄 **Assistente de IA dentro do curso:** abra o curso e clique em **StudioLMS** na navegação do curso. O assistente roda no contexto do curso e por isso nunca pede categoria ou nome de curso: o curso já existe.
* 🎚️ **Três níveis de geração**, escolhidos na tela inicial:
  * **Atividade ou recurso:** uma atividade ou recurso de qualquer um dos seis tipos (página, rótulo, questionário, fórum, tarefa ou glossário), na seção que você escolher (roda enquanto você espera).
  * **Seção:** a IA planeja as atividades de uma seção existente (ou nova) a partir de material de referência, você revisa o plano, e a seção é montada em segundo plano.
  * **Curso completo:** roteiro, revisão e geração em segundo plano com progresso ao vivo.
* 🧭 **Dois modos:** **Padrão** (estrutura pedagógica) e **Gamificado** (acrescenta o PlayerHUD; só aparece quando o `block_playerhud` está instalado).
* 📑 **Seis tipos de atividade, todos conteúdo nativo do Moodle:**
  * **Página:** conteúdo da IA em blocos ricos do Studio (infográficos, cards, destaques, grades) quando o `tiny_studiolms` está instalado, ou HTML semântico limpo sem ele, com um destaque automático de "Conceitos-chave" tirado do glossário do curso.
  * **Rótulo:** um título estilizado que separa grupos de atividades; gerado junto com as outras atividades da seção, também as lista.
  * **Questionário:** questões feitas pela IA (`multichoice`, `truefalse`, `shortanswer`) vão para o banco de questões e são ligadas ao questionário. A introdução mostra as regras (questões, tentativas, tempo). Com o `filter_studiolms` opcional ativo no curso, ela guarda a marca `[QUIZINFO]`, e as regras são calculadas a cada exibição, acompanhando mudanças e as exceções de cada estudante. Sem o filtro os números são escritos uma vez, na geração, e envelhecem se você mudar o questionário.
  * **Fórum:** uma introdução feita de blocos do Studio: o título, o texto de abertura, a pergunta norteadora, três passos para participar e uma dica.
  * **Tarefa:** um enunciado rico: o que entregar, os passos e os critérios de avaliação em tabela (pede-se à IA que não invente datas nem pontos).
  * **Glossário:** um glossário do curso, criado primeiro, com vinculação automática ligada e uma introdução que diz como usá-lo. Os termos dele alimentam os destaques de "Conceitos-chave" de todas as páginas.
* 🖌️ **Estilizar o que você já escreveu:** transforma uma página ou rótulo seu em blocos do Studio, numa cópia oculta. [Saiba mais](#restyle).
* 🧩 **Blocos editáveis:** os blocos gerados levam os invólucros `data-slms-block-type` e `data-slms-state`, e por isso reabrem para edição normal no editor `tiny_studiolms`. Modelos prontos são usados quando algum serve; senão a IA compõe os blocos na hora.
* 🛟 **Seguro por padrão:** o StudioLMS acrescenta conteúdo e não toca no que já existe. A opção "apagar antes" pede confirmação, avisa que o que os estudantes fizeram também some e nunca apaga o fórum de Avisos. Um roteiro sem seção com título é recusado antes de apagar qualquer coisa.
* ⏳ **Geração em segundo plano:** seções e cursos completos rodam numa tarefa adhoc com progresso ao vivo e um relatório final (seções, atividades por tipo, questões, termos do glossário, modelo por página).
* 🩹 **Reversão cirúrgica:** o curso nunca é apagado. Cada seção e módulo criado é registrado, e se uma etapa falha só eles são removidos.
* 🎮 **Modo gamificado (PlayerHUD):** reaproveita os geradores do próprio PlayerHUD: uma PlayerCoin com drop no fórum de avisos, um pacote de avatares, trocas de moeda por avatar e missões (determinísticos, sem IA), mais um capítulo narrativo e drops de itens temáticos feitos pela IA. Três perfis (Conquista, Narrativa, Social) ajustam XP, missões, ranking e drops; o Social também acrescenta um colecionável por hora a cada fórum gerado.
* 🌐 **Idioma do conteúdo:** tudo é gerado no idioma da interface do professor, passado explicitamente em cada prompt.
* 📊 **Auditoria e eventos:** um registro das gerações e os eventos `course_generated` e `generation_failed` no log do Moodle.
* 🔒 **API de Privacidade:** exportação e exclusão das três tabelas que guardam dados de um professor (registro de gerações, rascunho de roteiro, progresso).
