# Moodle Local Plugin StudioLMS

![Moodle](https://img.shields.io/badge/Moodle-4.5%2B-orange?style=flat&logo=moodle&logoColor=white)
![License](https://img.shields.io/badge/License-GPLv3-blue?style=flat)
![Status](https://img.shields.io/badge/Status-Beta-yellow?style=flat)
[![Latest Release](https://img.shields.io/github/v/release/jeanlucio/moodle-local_studiolms?style=flat)](https://github.com/jeanlucio/moodle-local_studiolms/releases)
[![Author](https://img.shields.io/badge/by-Jean_Lucio-6f42c1?style=flat)](https://marketplace.moodle.com/user/984)

[![Moodle Plugin CI](https://github.com/jeanlucio/moodle-local_studiolms/actions/workflows/ci.yml/badge.svg)](https://github.com/jeanlucio/moodle-local_studiolms/actions/workflows/ci.yml)
[![Last Commit](https://img.shields.io/github/last-commit/jeanlucio/moodle-local_studiolms?style=flat)](https://github.com/jeanlucio/moodle-local_studiolms/commits)
[![Open Issues](https://img.shields.io/github/issues/jeanlucio/moodle-local_studiolms?style=flat)](https://github.com/jeanlucio/moodle-local_studiolms/issues)

[English](#english) | [Português](#português)

---

## English

**StudioLMS** (`local_studiolms`) is an AI assistant that builds course content from inside a Moodle course that already exists. A teacher describes the topic, reviews what the AI proposes, and the course is filled with sections, rich Studio-styled pages, quizzes, forums, assignments and glossaries (and, optionally, PlayerHUD gamification). It can also restyle a page or label you already wrote into Studio blocks, in a hidden copy, without rewriting your text. Everything is native Moodle content.

📚 **[Full documentation](https://jeanlucio.github.io/moodle-local_studiolms/)**: the generation wizard, the restyle and its optional polish mode, the AI and the switch for AI tools of the course, the optional integrations, the automated tests, and security details.

### 📦 Requirements

| Component | Version |
|-----------|---------|
| Moodle    | 4.5 and later |
| PHP       | 8.1+ |

An AI source is needed to generate anything: the [AI Hub](https://github.com/jeanlucio/moodle-local_aihub) (`local_aihub`, a plugin by the same author) with a key, or a provider in Moodle's AI subsystem. Everything else is optional (`tiny_studiolms`, `filter_studiolms`, `block_playerhud`).

### 🛠️ Installation

1. Download the `.zip` file or clone this repository.
2. Extract the folder into your Moodle `local/` directory and name it `studiolms` (`your-moodle/local/studiolms/`).
3. Visit **Site administration > Notifications** to finish the installation.
4. Editing teachers and managers get `local/studiolms:generate` and `local/studiolms:restyle` by default. The **StudioLMS** item then appears in the course navigation.

### 🔎 Third-party service disclosure

StudioLMS generates content with AI, and relies entirely on the AI the site already provides: it ships **no API key and has no key screen**. The teacher's theme, any pasted reference material and, when restyling, the text of the page or label are sent to the provider the site resolved (the AI Hub or Moodle's `core_ai`), under that provider's own terms and privacy policy. Nothing is sent unless a generation or a restyle is started, and a course that has the AI tools turned off is never sent. StudioLMS does not store raw AI answers; it stores what the teacher typed in the wizard (theme and pasted material) in its own tables, exported and erased by the Privacy API. **Do not paste personal data into the reference field.**

---

## Português

O **StudioLMS** (`local_studiolms`) é um assistente de IA que monta conteúdo de curso de dentro de um curso do Moodle que já existe. O professor descreve o tema, revisa o que a IA propõe, e o curso é preenchido com seções, páginas ricas no estilo do Studio, questionários, fóruns, tarefas e glossários (e, opcionalmente, a gamificação do PlayerHUD). Ele também pode estilizar em blocos do Studio uma página ou rótulo que você já escreveu, numa cópia oculta, sem reescrever o seu texto. Tudo é conteúdo nativo do Moodle.

📚 **[Documentação completa](https://jeanlucio.github.io/moodle-local_studiolms/pt.html)**: o assistente de geração, a estilização e o modo opcional de polir, a IA e o interruptor de ferramentas de IA do curso, as integrações opcionais, os testes automatizados e os detalhes de segurança.

### 📦 Requisitos

| Componente | Versão |
|------------|--------|
| Moodle     | 4.5 ou superior |
| PHP        | 8.1+ |

É preciso uma fonte de IA para gerar qualquer coisa: o [Hub de IA](https://github.com/jeanlucio/moodle-local_aihub) (`local_aihub`, um plugin do mesmo autor) com uma chave, ou um provedor no subsistema de IA do Moodle. Todo o resto é opcional (`tiny_studiolms`, `filter_studiolms`, `block_playerhud`).

### 🛠️ Instalação

1. Baixe o arquivo `.zip` ou clone este repositório.
2. Extraia a pasta no diretório `local/` do seu Moodle e dê a ela o nome `studiolms` (`seu-moodle/local/studiolms/`).
3. Acesse **Administração do site > Notificações** para concluir a instalação.
4. Professores com edição e gerentes recebem `local/studiolms:generate` e `local/studiolms:restyle` por padrão. O item **StudioLMS** aparece então na navegação do curso.

### 🔎 Divulgação de serviço de terceiros

O StudioLMS gera conteúdo com IA e depende inteiramente da IA que o site já oferece: ele **não traz chave de API nem tem tela de chave**. O tema do professor, o material de referência colado e, na estilização, o texto da página ou rótulo são enviados ao provedor que o site resolveu (o Hub de IA ou o `core_ai` do Moodle), sob os termos e a política de privacidade desse provedor. Nada é enviado a menos que uma geração ou estilização seja iniciada, e um curso com as ferramentas de IA desligadas nunca é enviado. O StudioLMS não guarda respostas brutas da IA; guarda o que o professor digitou no assistente (tema e material colado) em tabelas próprias, exportadas e apagadas pela API de Privacidade. **Não cole dados pessoais no campo de referência.**

---

## 📄 License / Licença

GNU General Public License v3 (GPLv3) or later / ou posterior. See / Veja `COPYING.txt`.

**Copyright:** 2026 Jean Lúcio
