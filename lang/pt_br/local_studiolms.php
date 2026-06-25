<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Brazilian Portuguese language strings for the local_studiolms plugin.
 *
 * @package    local_studiolms
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
// phpcs:disable moodle.Files.LineLength

$string['activity_assign'] = 'Tarefa';
$string['activity_forum'] = 'Fórum';
$string['activity_glossary'] = 'Glossário';
$string['activity_heading'] = 'Gerar uma atividade';
$string['activity_label'] = 'Rótulo';
$string['activity_page'] = 'Página';
$string['activity_quiz'] = 'Quiz';
$string['activity_section'] = 'Seção de destino';
$string['activity_success'] = 'Atividade criada com sucesso.';
$string['activity_view'] = 'Ver atividade';
$string['add_activity'] = 'Adicionar atividade';
$string['add_objective'] = 'Adicionar objetivo';
$string['add_section'] = 'Adicionar seção';
$string['aiheading'] = 'Inteligência artificial';
$string['aikeys_info'] = 'O StudioLMS gera conteúdo via hub PlayerGames (local_playergames) quando instalado, pelas chaves do editor StudioLMS (tiny_studiolms) quando presente, ou pelo core_ai do Moodle. Não há chave de API para configurar aqui — configure um provedor de IA em Administração do site → IA, ou nas configurações do PlayerGames.';
$string['aria_activity_title'] = 'Título da atividade';
$string['aria_activity_type'] = 'Tipo da atividade';
$string['aria_objective'] = 'Objetivo de aprendizagem';
$string['aria_remove_activity'] = 'Remover atividade';
$string['aria_remove_objective'] = 'Remover objetivo';
$string['aria_remove_section'] = 'Remover seção';
$string['aria_section_title'] = 'Título da seção';
$string['back_to_course'] = 'Voltar ao curso';
$string['bloom_analyze'] = 'Analisar';
$string['bloom_apply'] = 'Aplicar';
$string['bloom_create'] = 'Criar';
$string['bloom_evaluate'] = 'Avaliar';
$string['bloom_general'] = 'Geral';
$string['bloom_remember'] = 'Lembrar';
$string['bloom_taxonomy'] = 'Taxonomia de Bloom';
$string['bloom_understand'] = 'Entender';
$string['briefing_bloom'] = 'Nível cognitivo predominante';
$string['briefing_mode'] = 'Modo';
$string['briefing_reference'] = 'Material de referência (opcional — cole o texto)';
$string['briefing_reference_placeholder'] = 'Cole ementa, anotações ou qualquer texto de referência que a IA deva considerar.';
$string['briefing_structure'] = 'Estrutura';
$string['briefing_theme'] = 'Tema / foco do conteúdo';
$string['briefing_theme_placeholder'] = 'Por exemplo: Introdução ao Python';
$string['briefing_wipe'] = 'Limpar o curso antes (apaga seções e atividades atuais)';
$string['briefing_wipe_warning'] = 'Isso remove permanentemente as seções e atividades existentes deste curso antes de gerar. Use com cuidado.';
$string['btn_back'] = 'Voltar';
$string['btn_cancel'] = 'Cancelar';
$string['btn_generate_activity'] = 'Gerar atividade';
$string['btn_generate_outline'] = 'Gerar curso';
$string['btn_generate_section'] = 'Gerar seção';
$string['btn_plan_section'] = 'Planejar seção';
$string['btn_populate'] = 'Preencher curso';
$string['courseplantitle'] = 'Plano de Disciplina';
$string['error_outline_generation'] = 'Não foi possível gerar o outline. Tente novamente.';
$string['error_populate'] = 'Não foi possível preencher o curso. Tente novamente.';
$string['error_section_plan'] = 'Não foi possível gerar o plano da seção. Tente novamente.';
$string['error_theme_required'] = 'Informe o tema ou foco do conteúdo.';
$string['estimate_time'] = 'Tempo estimado: ~{$a} min';
$string['estimate_time_short'] = 'Tempo estimado: < 1 min';
$string['event_course_generated'] = 'Curso preenchido com o StudioLMS';
$string['event_generation_failed'] = 'Falha na geração do StudioLMS';
$string['fillwithai'] = 'StudioLMS';
$string['gamification_profile'] = 'Perfil de gamificação';
$string['generate_heading'] = 'Gerar curso com StudioLMS';
$string['generating'] = 'Gerando curso...';
$string['glossary_default_title'] = 'Glossário do curso';
$string['glossary_intro'] = 'Termos-chave deste curso.';
$string['invalidairesponse'] = 'A IA não retornou um outline de curso válido. Tente novamente.';
$string['landing_activity_desc'] = 'Gera uma atividade avulsa numa seção existente deste curso.';
$string['landing_activity_title'] = 'Atividade';
$string['landing_coming_soon'] = 'Em breve';
$string['landing_course_desc'] = 'Gera a estrutura completa do curso a partir de um briefing e outline.';
$string['landing_course_title'] = 'Curso Completo';
$string['landing_heading'] = 'Olá {$a}, o que você quer fazer hoje?';
$string['landing_section_desc'] = 'Gera uma seção completa a partir do seu material de referência.';
$string['landing_section_title'] = 'Seção';
$string['mode_gamified'] = 'Gamificado';
$string['mode_gamified_detected'] = 'PlayerHUD detectado';
$string['mode_gamified_disabled'] = 'Instale o Plugin PlayerHUD para ativar este modo';
$string['mode_standard'] = 'Padrão';
$string['noai_admin'] = 'Você pode habilitar um agora: abra a IA do Moodle e configure um provedor para geração de texto.';
$string['noai_adminlink'] = 'Configurar a IA do Moodle';
$string['noai_heading'] = 'Provedor de IA necessário';
$string['noai_intro'] = 'O StudioLMS monta o conteúdo do curso com IA, então um provedor de IA precisa estar disponível antes de gerar qualquer coisa.';
$string['noai_teacher'] = 'Peça ao administrador do site para habilitar um provedor de IA (Administração do site → IA → Provedores de IA), ou para instalar o hub PlayerGames (local_playergames) para você adicionar uma chave pessoal.';
$string['noaiprovider'] = 'Nenhum provedor de IA disponível. Configure um provedor na IA do Moodle (Administração do site → IA → Provedores de IA), instale o hub PlayerGames (local_playergames) e cadastre uma chave de API, ou configure chaves no editor StudioLMS (tiny_studiolms).';
$string['objectives_heading'] = 'Objetivos de aprendizagem';
$string['outline_review_heading'] = 'Revisar curso';
$string['pluginname'] = 'StudioLMS construtor de cursos';
$string['populate_heading'] = 'Preenchendo o curso';
$string['pretraining_title'] = 'Conceitos-chave';
$string['privacy:metadata:local_studiolms_generation_log'] = 'Um registro das gerações de curso concluídas executadas pelo professor.';
$string['privacy:metadata:local_studiolms_generation_log:bloomlevel'] = 'O nível da taxonomia de Bloom selecionado.';
$string['privacy:metadata:local_studiolms_generation_log:courseid'] = 'O curso que foi preenchido.';
$string['privacy:metadata:local_studiolms_generation_log:gamificationprofile'] = 'O perfil de gamificação selecionado.';
$string['privacy:metadata:local_studiolms_generation_log:mode'] = 'O modo de geração (padrão ou gamificado).';
$string['privacy:metadata:local_studiolms_generation_log:outlinejson'] = 'O outline do curso aprovado.';
$string['privacy:metadata:local_studiolms_generation_log:prompt'] = 'O tema ou prompt informado pelo professor.';
$string['privacy:metadata:local_studiolms_generation_log:status'] = 'O status final da geração.';
$string['privacy:metadata:local_studiolms_generation_log:timecompleted'] = 'O momento em que a geração foi concluída.';
$string['privacy:metadata:local_studiolms_generation_log:timecreated'] = 'O momento em que a geração começou.';
$string['privacy:metadata:local_studiolms_generation_log:userid'] = 'O professor que executou a geração.';
$string['privacy:metadata:local_studiolms_outline'] = 'Rascunhos de outline salvos entre os passos do assistente.';
$string['privacy:metadata:local_studiolms_outline:briefingjson'] = 'O briefing informado no passo 1 (tema, modo, nível).';
$string['privacy:metadata:local_studiolms_outline:courseid'] = 'O curso de destino do rascunho.';
$string['privacy:metadata:local_studiolms_outline:outlinejson'] = 'O outline gerado mais as edições do professor.';
$string['privacy:metadata:local_studiolms_outline:status'] = 'O status do rascunho.';
$string['privacy:metadata:local_studiolms_outline:timecreated'] = 'O momento em que o rascunho foi criado.';
$string['privacy:metadata:local_studiolms_outline:timemodified'] = 'O momento da última modificação do rascunho.';
$string['privacy:metadata:local_studiolms_outline:userid'] = 'O professor dono do rascunho.';
$string['privacy:metadata:local_studiolms_progress'] = 'Registros de progresso da geração em segundo plano.';
$string['privacy:metadata:local_studiolms_progress:courseid'] = 'O curso sendo preenchido.';
$string['privacy:metadata:local_studiolms_progress:errormsg'] = 'A mensagem de erro quando a geração falhou.';
$string['privacy:metadata:local_studiolms_progress:status'] = 'O status do progresso.';
$string['privacy:metadata:local_studiolms_progress:timecreated'] = 'O momento em que o registro de progresso foi criado.';
$string['privacy:metadata:local_studiolms_progress:timemodified'] = 'O momento da última atualização do registro de progresso.';
$string['privacy:metadata:local_studiolms_progress:userid'] = 'O professor cuja geração é acompanhada.';
$string['profile_conquest'] = 'Conquista';
$string['profile_narrative'] = 'Narrativa';
$string['profile_social'] = 'Social';
$string['progress_activity'] = 'Atividade criada: {$a}';
$string['progress_avatars'] = 'Pacote de avatares criado.';
$string['progress_done'] = 'Curso preenchido.';
$string['progress_drops'] = 'Drops de itens adicionados: {$a}';
$string['progress_playercoin'] = 'PlayerCoin criada.';
$string['progress_playerhud'] = 'PlayerHUD configurado.';
$string['progress_quests'] = 'Missões criadas: {$a}';
$string['progress_section'] = 'Seção adicionada: {$a}';
$string['progress_social_forum'] = 'Coletável horário adicionado aos fóruns.';
$string['progress_story'] = 'Capítulo narrativo gerado.';
$string['progress_trades'] = 'Trocas criadas: {$a}';
$string['report_activities'] = 'Atividades geradas:';
$string['report_blocks'] = 'Blocos personalizados';
$string['report_degraded'] = 'simplificado';
$string['report_duration'] = 'Tempo total: {$a}';
$string['report_fallback'] = 'Simplificado (IA indisponível)';
$string['report_heading'] = 'Relatório de geração';
$string['report_pages'] = 'Páginas geradas:';
$string['report_plan'] = 'Plano de Disciplina (preset)';
$string['report_success'] = 'com sucesso';
$string['section_done'] = 'Seção preenchida.';
$string['section_generate_another'] = 'Gerar outra seção';
$string['section_heading'] = 'Gerar seção';
$string['section_new'] = 'Nova seção (adicionar ao final do curso)';
$string['section_number'] = 'Seção {$a}';
$string['section_plan_add'] = 'Adicionar atividade';
$string['section_plan_heading'] = 'Plano de atividades';
$string['section_planning'] = 'Planejando atividades da seção...';
$string['section_success'] = 'Seção preenchida com sucesso.';
$string['section_view'] = 'Ver seção';
$string['section_wipe'] = 'Sobrescrever seção (apaga as atividades atuais)';
$string['section_wipe_warning'] = 'Isso remove permanentemente as atividades existentes desta seção antes de gerar. Use com cuidado.';
$string['section_wiping'] = 'Removendo atividades existentes...';
$string['step_of'] = 'Passo {$a->current} de {$a->total}';
$string['structure_abc'] = 'ABC Learning Design';
$string['structure_free'] = 'Livre (IA decide)';
$string['studiolms:generate'] = 'Preencher um curso com IA usando o StudioLMS';
$string['studiolms:viewlog'] = 'Ver o log de gerações do StudioLMS';
$string['task_generate_course'] = 'Preencher um curso com o StudioLMS';
$string['task_generate_section'] = 'Preencher uma seção com o StudioLMS';
$string['warnings_heading'] = 'Algumas atividades usaram conteúdo simplificado por indisponibilidade da IA:';
