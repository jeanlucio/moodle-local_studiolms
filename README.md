# Moodle Local Plugin StudioLMS

[![Moodle Plugin CI](https://github.com/jeanlucio/moodle-local_studiolms/actions/workflows/ci.yml/badge.svg)](https://github.com/jeanlucio/moodle-local_studiolms/actions/workflows/ci.yml)
![Moodle](https://img.shields.io/badge/Moodle-4.5%2B-orange?style=flat-square&logo=moodle&logoColor=white)
![License](https://img.shields.io/badge/License-GPLv3-blue?style=flat-square)
![Status](https://img.shields.io/badge/Status-Beta-yellow?style=flat-square)

[English](#english) | [Português](#português)

---

## English

**StudioLMS** (`local_studiolms`) is the AI course builder of the StudioLMS ecosystem. Working **from inside an existing course**, a teacher describes the topic (or pastes reference material), picks a mode, reviews the AI-generated outline and confirms — the course is then filled with sections, rich Studio-styled pages, activities and, optionally, PlayerHUD gamification. Everything is produced as **100% native Moodle content** — no lock-in, editable through the standard Moodle interface like any other course.

It deliberately works on a course that **already exists**: editing your own course (`manageactivities`) is what an `editingteacher` actually has, whereas creating a course from scratch needs admin-level `course:create`. This makes the tool genuinely accessible to teachers.

---

### ✨ Features

* 🪄 **In-Course AI Wizard:** Launched from the course navigation ("Fill with AI"), the wizard runs in the course context — it never asks for a category or course name, because those already exist.
* 🎚️ **Three Generation Granularities:** A card-based landing lets the teacher choose how much to generate:
  * **Activity** — a single standalone activity (quiz, page, glossary) in an existing section (~30 s, foreground).
  * **Section** — a full section built from section-specific reference material, reviewed section by section.
  * **Full course** — outline → review → background generation with live progress.
* 🧭 **Two Modes:** **Standard** (pedagogical structure) and **Gamified** (adds PlayerHUD; shown only when `block_playerhud` is installed).
* 📑 **Native Activities Generated:**
  * **Page** (`mod_page`) — AI content rendered with rich Studio blocks (infographics, cards, callouts, grids) when `tiny_studiolms` is installed, or clean semantic HTML as a fallback; opens an automatic "Key concepts" (Pre-training) callout sourced from the course glossary.
  * **Label** (`mod_label`) — styled visual heading separating activity groups.
  * **Quiz** (`mod_quiz`) — AI-generated questions added to the question bank and linked to the quiz (`multichoice`, `truefalse`, `shortanswer`), with on-demand preview before committing.
  * **Forum** (`mod_forum`) — AI-generated guiding question and debate context.
  * **Assignment** (`mod_assign`) — AI-generated brief and expected criteria.
  * **Glossary** (`mod_glossary`) — one course glossary created first, with auto-linking enabled; its terms feed the Pre-training callouts on every page.
* 🧩 **Editable Rich Blocks:** Generated blocks carry the `data-slms-block-type` / `data-slms-state` wrappers, so they reopen for normal editing inside the `tiny_studiolms` editor. Ready-made presets are applied when one fits; otherwise the AI generates rich blocks on the fly (hybrid strategy).
* 🛟 **Safe by Default:** StudioLMS **adds** content without touching what already exists. An opt-in "Clean the course first" option is destructive, clearly warned and confirmed separately.
* ⏳ **Background Generation:** Full-course generation runs in an adhoc task with real-time progress polling and a structured final report (sections, activities by type, quiz questions, glossary terms, preset per page).
* 🩹 **Surgical Rollback:** The course is never deleted. Each created section and module id is tracked; if any step fails, only those items are removed, leaving the course and any pre-existing content intact.
* 🎮 **Gamified Mode (PlayerHUD):** Reuses PlayerHUD's own generators — PlayerCoin with a news-forum drop, avatar pack, coin-to-avatar trades and heuristic quests (all deterministic, no AI) — plus an AI narrative chapter and themed AI item drops. Three profiles (Conquest, Narrative, Social) tune XP, quests, ranking visibility and drops.
* 🌐 **Content Language:** All content is generated in the teacher's interface language (`current_language()`), passed explicitly in every AI prompt.
* 📊 **Audit & Events:** A generation log plus `course_generated` and `generation_failed` events recorded in Moodle's standard log store.
* 🔒 **Privacy API:** Full GDPR/LGPD compliance — export and deletion of the three teacher-owned tables (generation log, draft outline, progress).

---

### 🧠 Pedagogical Frameworks

* **Bloom's Taxonomy (teacher-visible):** the wizard asks for the predominant cognitive level, which shapes the type and tone of generated activities.
* **Backward Design (behind the scenes):** the AI generates objectives → assessments → learning activities, producing a course coherent across all three.
* **ABC Learning Design (optional preset):** a balanced set of activity types per section.
* **Octalysis (gamified mode):** PlayerHUD features are mapped to the eight core drives.

---

### 🔗 Optional Integrations

StudioLMS runs standalone on Moodle 4.5+ alone. Every integration is **soft** (`class_exists` / component detection) — there are **no hard `$plugin->dependencies`**.

| Plugin | Role when present |
|---|---|
| `tiny_studiolms` | Rich visual templates for generated pages (and an AI text fallback for keys of its own); without it, pages fall back to clean semantic HTML |
| `block_playerhud` | Enables the gamified mode |
| `local_aihub` | AI Hub — BYOK API keys (personal → site) |
| `local_aiassess` | AI rubrics for assignments/forums (planned) |

---

### 🤖 AI Resolution

StudioLMS has **no key widget of its own** — there is no key to configure in it. Free-text generation is resolved in this order, every step soft:

| Order | Source |
|------|--------|
| 1 | **`local_aihub`** — when installed and holding a BYOK key (personal → site) |
| 2 | **`tiny_studiolms` own keys** — when the editor is installed and has a key of its own (personal → site), used only when the hub is absent |
| 3 | **Moodle `core_ai`** — called directly; no external key needed when the site already has a provider configured |
| 4 | A clear message asks the admin to configure AI (Admin → AI) or install the hub |

The whole chain lives inside StudioLMS's `ai_resolver`; **no other plugin is modified.** AI calls use batching (one call per quiz / per outline) and a retry-with-backoff for malformed JSON to stay friendly to free provider tiers.

---

### 🎓 Educational Purpose

StudioLMS is designed to:

* Turn days of manual course setup into minutes
* Give teachers — not just admins — an accessible, in-course course builder
* Produce pedagogically structured content (not just plain text), grounded in learning science
* Keep the output fully native and lock-in free, editable like any Moodle course
* Optionally layer motivation through PlayerHUD gamification

---

### 🔬 Learning Science Foundations

The rich visual output is not cosmetic — each block type is grounded in established learning science.

**Multimedia Learning Theory — Richard Mayer (2001):** generated pages implement seven of Mayer's principles (signalling, segmenting, spatial contiguity, coherence, redundancy, pre-training, personalization). The automatic "Key concepts" callout at the top of each page is a direct application of *pre-training*.

**Cognitive Load Theory — John Sweller (1988):** organised visual blocks (cards, grids, steps) reduce *extraneous* load and free capacity for *germane* load. A single course glossary means students always know where to find definitions.

**Dual Coding Theory — Allan Paivio (1971):** infographics, charts, gauges and mind maps engage the visual channel while text complements through the verbal channel.

**Universal Design for Learning — CAST (2002):** the same concept can be presented as text, visual, table or diagram, expanding accessibility across learning styles.

**Information Mapping — Robert Horn (1970s):** each information type maps to an optimal block (procedure → steps, chronological process → timeline, comparison → comparison, quantitative facts → stats/chart/gauge, hierarchy → mind map, related items → grid cards, critical info → callout).

---

### 📦 Requirements

| Component | Version |
|-----------|---------|
| Moodle    | 4.5+    |
| PHP       | 8.1+    |

---

### 🛠️ Installation

1. Download the `.zip` file or clone this repository.
2. Extract the folder into your Moodle `local/` directory.
3. Rename the folder to `studiolms` (if necessary).
   Final path:
   `your-moodle/local/studiolms/`
4. Visit **Site administration > Notifications** to complete the database installation.
5. Grant the `local/studiolms:generate` capability to roles that should use the wizard (Teacher and Manager by default).
6. The **Fill with AI (Studio)** button will appear in the course navigation for authorised users.

---

### 📖 Usage

1. Open the course you want to fill and click **Fill with AI (Studio)** in the course navigation (also shown as a prominent button when the course is empty).
2. On the landing, choose a generation granularity: **Activity**, **Section** or **Full course**.
3. **Briefing:** describe the topic (or paste reference material), choose the Bloom level, the structure, and the mode (Standard or Gamified). Optionally tick "Clean the course first".
4. **Outline review** (full-course mode): rename, remove, reorder sections; preview a quiz's questions on demand.
5. **Generation:** confirm and watch the live progress; for the full course this runs in the background. When done, return to the course — now populated, without touching anything that already existed.

There are **no dates** in any step — the teacher edits them through the standard Moodle interface after generation.

---

### 🔐 Security & Compliance

* Access always validated in the **course context**: `require_login($course)` + `require_capability('local/studiolms:generate', context_course)`, re-checked in the web service and inside the adhoc task (`set_userid` then re-validate).
* **No course creation:** the wizard operates on an existing course, eliminating the `course:create` attack surface entirely.
* The destructive "Clean the course first" option additionally requires `moodle/course:manageactivities` and an explicit confirmation.
* `require_sesskey()` on every state-changing call; all services declared in `db/services.php`.
* Generated HTML passes through `format_text()`; AI JSON is schema-validated before processing — a validation failure ends generation with a clear error and triggers the surgical rollback.
* The course is **never** deleted — only the items StudioLMS created are rolled back on failure.
* Pasted reference material is validated server-side (`PARAM_TEXT`, max length) and **never persisted** — used only to compose the prompt and discarded.
* Privacy-aware: full GDPR/LGPD data export and deletion via the Privacy API.

---

### 🔎 Third-party Service Disclosure

StudioLMS generates course content using AI. AI is **existential** to the plugin (a course cannot be generated without it), but it relies entirely on AI that the site already provides — StudioLMS ships **no API key and no key widget of its own**.

The teacher's prompt and any pasted reference material are transmitted to the resolved provider (the `local_aihub` broker, the `tiny_studiolms` editor's configured provider, or Moodle's `core_ai`) for processing. External services operate under their own terms of service and privacy policies. StudioLMS does not store prompts or raw AI responses; only the generated course content the teacher confirms is saved into the course.

No external communication occurs unless a generation is explicitly started.

---

## 📄 License / Licença

This project is licensed under the **GNU General Public License v3 (GPLv3)**.

**Copyright:** 2026 Jean Lúcio

---

## Português

O **StudioLMS** (`local_studiolms`) é o gerador de cursos por IA do ecossistema StudioLMS. Atuando **de dentro de um curso já existente**, o professor descreve o tema (ou cola material de referência), escolhe o modo, revisa o outline gerado pela IA e confirma — o curso é então preenchido com seções, páginas com visual rico do Studio, atividades e, opcionalmente, gamificação PlayerHUD. Tudo é produzido como **conteúdo 100% nativo do Moodle** — sem lock-in, editável pela interface padrão como qualquer outro curso.

A ferramenta atua deliberadamente sobre um curso que **já existe**: editar o próprio curso (`manageactivities`) é o que um `editingteacher` de fato tem, enquanto criar curso do zero exige o `course:create` de admin. Isso a torna genuinamente acessível ao professor.

---

### ✨ Funcionalidades

* 🪄 **Wizard de IA dentro do curso:** Aberto pela navegação do curso ("Preencher com IA"), o wizard roda no contexto do curso — nunca pergunta categoria ou nome, pois já existem.
* 🎚️ **Três granularidades de geração:** Uma tela inicial em cards permite escolher quanto gerar:
  * **Atividade** — uma atividade avulsa (quiz, página, glossário) numa seção existente (~30 s, primeiro plano).
  * **Seção** — uma seção completa a partir de material de referência específico, revisada seção a seção.
  * **Curso completo** — outline → revisão → geração em segundo plano com progresso ao vivo.
* 🧭 **Dois modos:** **Padrão** (estrutura pedagógica) e **Gamificado** (adiciona o PlayerHUD; exibido apenas quando o `block_playerhud` está instalado).
* 📑 **Atividades nativas geradas:**
  * **Página** (`mod_page`) — conteúdo da IA renderizado com blocos ricos do Studio (infográficos, cards, callouts, grids) quando o `tiny_studiolms` está instalado, ou HTML semântico limpo como fallback; abre um callout automático de "Conceitos-chave" (Pre-training) com os termos do glossário do curso.
  * **Rótulo** (`mod_label`) — título visual estilizado separando grupos de atividades.
  * **Quiz** (`mod_quiz`) — questões geradas por IA adicionadas ao banco de questões e vinculadas ao quiz (`multichoice`, `truefalse`, `shortanswer`), com pré-visualização sob demanda antes de confirmar.
  * **Fórum** (`mod_forum`) — questão norteadora e contexto de debate gerados por IA.
  * **Tarefa** (`mod_assign`) — enunciado e critérios esperados gerados por IA.
  * **Glossário** (`mod_glossary`) — um glossário do curso criado primeiro, com link automático habilitado; seus termos alimentam os callouts de Pre-training de cada página.
* 🧩 **Blocos ricos editáveis:** Os blocos gerados carregam os wrappers `data-slms-block-type` / `data-slms-state`, então reabrem para edição normal no editor `tiny_studiolms`. Presets prontos são aplicados quando há um adequado; caso contrário a IA gera blocos ricos na hora (estratégia híbrida).
* 🛟 **Seguro por padrão:** O StudioLMS **adiciona** conteúdo sem tocar no que já existe. A opção "Limpar o curso antes" é opt-in, destrutiva, exibida com aviso e confirmada à parte.
* ⏳ **Geração em segundo plano:** A geração de curso completo roda numa adhoc task com polling de progresso em tempo real e um relatório final estruturado (seções, atividades por tipo, questões do quiz, termos do glossário, preset por página).
* 🩹 **Rollback cirúrgico:** O curso nunca é apagado. Cada seção e cmid criado é registrado; se alguma etapa falha, apenas esses itens são removidos, deixando o curso e qualquer conteúdo preexistente intactos.
* 🎮 **Modo Gamificado (PlayerHUD):** Reaproveita os geradores do próprio PlayerHUD — PlayerCoin com drop no fórum de avisos, pacote de avatares, trocas moeda→avatar e quests heurísticas (tudo determinístico, sem IA) — mais um capítulo narrativo por IA e drops temáticos por IA. Três perfis (Conquista, Narrativa, Social) modulam XP, quests, visibilidade do ranking e drops.
* 🌐 **Idioma do conteúdo:** Todo o conteúdo é gerado no idioma da interface do professor (`current_language()`), passado explicitamente em cada prompt de IA.
* 📊 **Auditoria e eventos:** Um log de gerações mais os eventos `course_generated` e `generation_failed` registrados no log padrão do Moodle.
* 🔒 **Privacy API:** Conformidade total com LGPD/GDPR — exportação e exclusão das três tabelas do professor (log de gerações, rascunho de outline, progresso).

---

### 🧠 Frameworks Pedagógicos

* **Taxonomia de Bloom (visível ao professor):** o wizard pergunta o nível cognitivo predominante, que molda o tipo e o tom das atividades geradas.
* **Backward Design (nos bastidores):** a IA gera objetivos → avaliações → atividades de aprendizagem, produzindo um curso coerente entre os três.
* **ABC Learning Design (preset opcional):** um conjunto balanceado de tipos de atividade por seção.
* **Octalysis (modo gamificado):** os recursos do PlayerHUD são mapeados aos oito drives centrais.

---

### 🔗 Integrações Opcionais

O StudioLMS funciona de forma autônoma apenas com o Moodle 4.5+. Toda integração é **soft** (`class_exists` / detecção de componente) — **não há `$plugin->dependencies` rígidas**.

| Plugin | Papel quando presente |
|---|---|
| `tiny_studiolms` | Templates visuais ricos nas páginas geradas (e fallback de IA com chaves próprias); sem ele, as páginas caem em HTML semântico limpo |
| `block_playerhud` | Habilita o modo gamificado |
| `local_aihub` | Central de IA — chaves de API BYOK (pessoal → site) |
| `local_aiassess` | Rubricas por IA em tarefas/fóruns (planejado) |

---

### 🤖 Resolução de IA

O StudioLMS **não tem widget de chaves próprio** — não há chave para configurar nele. A geração de texto livre é resolvida nesta ordem, cada etapa soft:

| Ordem | Fonte |
|------|--------|
| 1 | **`local_aihub`** — quando instalada e com chave BYOK (pessoal → site) |
| 2 | **Chaves próprias do `tiny_studiolms`** — quando o editor está instalado e tem chave própria (pessoal → site), usado apenas quando o hub está ausente |
| 3 | **`core_ai` do Moodle** — chamada direta; nenhuma chave externa necessária quando o site já tem um provedor configurado |
| 4 | Uma mensagem clara orienta o admin a configurar a IA (Admin → IA) ou instalar o hub |

Toda a cadeia vive dentro do `ai_resolver` do StudioLMS; **nenhum outro plugin é modificado.** As chamadas usam batching (uma chamada por quiz / por outline) e retry com backoff para JSON malformado, mantendo compatibilidade com os planos gratuitos de provedores.

---

### 🎓 Finalidade Educacional

O StudioLMS foi projetado para:

* Transformar dias de montagem manual de curso em minutos
* Dar ao professor — não só ao admin — um gerador de cursos acessível, dentro do curso
* Produzir conteúdo pedagogicamente estruturado (não apenas texto corrido), com base em ciência da aprendizagem
* Manter o resultado totalmente nativo e sem lock-in, editável como qualquer curso do Moodle
* Opcionalmente adicionar motivação por meio da gamificação PlayerHUD

---

### 🔬 Fundamentos em Ciência da Aprendizagem

O visual rico não é cosmético — cada tipo de bloco tem base em ciência da aprendizagem consolidada.

**Teoria da Aprendizagem Multimídia — Richard Mayer (2001):** as páginas geradas implementam sete princípios de Mayer (signaling, segmenting, spatial contiguity, coherence, redundancy, pre-training, personalization). O callout automático de "Conceitos-chave" no início de cada página é uma aplicação direta do *pre-training*.

**Teoria da Carga Cognitiva — John Sweller (1988):** blocos visuais organizados (cards, grids, steps) reduzem a carga *estranha* e liberam capacidade para a carga *germânica*. Um único glossário do curso garante que o aluno sempre saiba onde procurar definições.

**Teoria da Codificação Dual — Allan Paivio (1971):** infográficos, gráficos, gauges e mapas mentais acionam o canal visual enquanto o texto complementa pelo canal verbal.

**Universal Design for Learning — CAST (2002):** o mesmo conceito pode ser apresentado como texto, visual, tabela ou diagrama, ampliando a acessibilidade para diferentes estilos de aprendizagem.

**Information Mapping — Robert Horn (décadas de 1970):** cada tipo de informação mapeia para um bloco ideal (procedimento → passos, processo cronológico → linha do tempo, comparação → comparação, fatos quantitativos → stats/gráfico/gauge, hierarquia → mapa mental, itens relacionados → grid de cards, informação crítica → callout).

---

### 📦 Requisitos

| Componente | Versão |
|------------|--------|
| Moodle     | 4.5+   |
| PHP        | 8.1+   |

---

### 🛠️ Instalação

1. Baixe o arquivo `.zip` ou clone este repositório.
2. Extraia na pasta `local/` do seu Moodle.
3. Renomeie para `studiolms` (se necessário).
   Caminho final:
   `seu-moodle/local/studiolms/`
4. Acesse **Administração do site > Notificações** para concluir a instalação do banco de dados.
5. Conceda a capability `local/studiolms:generate` aos perfis que devem usar o wizard (Professor e Gerente por padrão).
6. O botão **Preencher com IA (Studio)** aparecerá na navegação do curso para os usuários autorizados.

---

### 📖 Como Usar

1. Abra o curso que deseja preencher e clique em **Preencher com IA (Studio)** na navegação do curso (também exibido como botão em destaque quando o curso está vazio).
2. Na tela inicial, escolha a granularidade da geração: **Atividade**, **Seção** ou **Curso completo**.
3. **Briefing:** descreva o tema (ou cole material de referência), escolha o nível de Bloom, a estrutura e o modo (Padrão ou Gamificado). Opcionalmente marque "Limpar o curso antes".
4. **Revisão do outline** (modo curso completo): renomeie, remova, reordene seções; pré-visualize as questões de um quiz sob demanda.
5. **Geração:** confirme e acompanhe o progresso ao vivo; no curso completo isso roda em segundo plano. Ao concluir, volte ao curso — agora preenchido, sem tocar em nada que já existisse.

**Não há datas** em nenhum passo — o professor as edita pela interface padrão do Moodle após a geração.

---

### 🔐 Segurança e Conformidade

* Acesso sempre validado no **contexto do curso**: `require_login($course)` + `require_capability('local/studiolms:generate', context_course)`, revalidado no web service e dentro da adhoc task (`set_userid` e revalidação).
* **Sem criação de curso:** o wizard opera sobre um curso existente, eliminando por completo a superfície de risco do `course:create`.
* A opção destrutiva "Limpar o curso antes" exige adicionalmente `moodle/course:manageactivities` e uma confirmação explícita.
* `require_sesskey()` em toda chamada que altera estado; todos os serviços declarados em `db/services.php`.
* O HTML gerado passa por `format_text()`; o JSON da IA é validado por schema antes de processar — uma falha de validação encerra a geração com erro claro e dispara o rollback cirúrgico.
* O curso **nunca** é apagado — apenas os itens que o StudioLMS criou sofrem rollback em caso de falha.
* O material de referência colado é validado no servidor (`PARAM_TEXT`, tamanho máximo) e **nunca persistido** — usado apenas para compor o prompt e descartado.
* Privacidade: exportação e exclusão completa de dados via Privacy API (LGPD/GDPR).

---

### 🔎 Divulgação de Serviço de Terceiros

O StudioLMS gera conteúdo de curso usando IA. A IA é **existencial** ao plugin (não há como gerar um curso sem ela), mas o plugin depende inteiramente da IA que o site já fornece — o StudioLMS **não traz nenhuma chave de API nem widget de chave próprio**.

O prompt do professor e qualquer material de referência colado são enviados ao provedor resolvido (o intermediário `local_aihub`, o provedor configurado do editor `tiny_studiolms`, ou o `core_ai` do Moodle) para processamento. Os serviços externos seguem seus próprios termos de uso e políticas de privacidade. O StudioLMS não armazena prompts nem respostas brutas da IA; apenas o conteúdo de curso gerado que o professor confirma é salvo no curso.

Nenhuma comunicação externa ocorre sem que uma geração seja explicitamente iniciada.

---

## 📄 Licença

Este projeto é licenciado sob a **GNU General Public License v3 (GPLv3)**.

**Copyright:** 2026 Jean Lúcio
