# 🖌️ Estilizar o que você já escreveu

Muitas vezes o professor já tem o texto: colado de um documento ou de uma página da web, sem títulos de verdade. A **estilização** dá a ele a estrutura dos blocos do Studio sem reescrevê-lo.

## Como funciona

1. Na tela inicial escolha **Estilizar o que já existe**, ou abra uma página e escolha **Estilizar com o StudioLMS** nas abas de configurações dela. A lista mostra as páginas e rótulos do curso, por seção.
2. Clique em **Estilizar** em uma delas.
3. A atividade é **duplicada**. O original nunca é alterado: o novo layout vai para uma **cópia oculta** logo depois dele, com o nome "Título (StudioLMS)".
4. Abra a cópia para ajustar no editor, veja como ficou e publique (ou apague) quando estiver satisfeito.

Só é possível estilizar **páginas e rótulos**, e só quando o texto é HTML. É preciso a capacidade `local/studiolms:restyle` e as capacidades do Moodle para fazer backup, restaurar e adicionar esse tipo de atividade.

## O que a IA faz, e o que não faz

O texto é cortado em linhas (um parágrafo digitado com quebras de linha vira um trecho por linha, e assim um título escrito como linha de um parágrafo pode ser encontrado). A IA vê tudo, numerado, e responde **só papéis e grupos de números de linha**: um título, uma lista, um destaque, um card, uma grade de itens paralelos, passos ou uma seção recolhida. As palavras de cada bloco são as originais, colocadas ali pelo plugin. **Nenhuma palavra é perdida ou movida**: um teste confere que toda palavra do original está no resultado, na mesma ordem.

Imagens, vídeos, tabelas e blocos do Studio que já estão no texto são mantidos exatamente como estão. Uma linha que termina em dois-pontos, ou que é uma frase completa, nunca vira título.

O resultado é feito por uma IA, então duas execuções no mesmo texto podem diferir um pouco. Estilizar a mesma página de novo apenas faz outra cópia.

## Reorganizar e polir o texto (opcional)

Uma caixa na lista, **desligada por padrão**, deixa a IA ir além: achar títulos onde o texto não tem, tirar repetições, dividir parágrafos longos, encurtar os prolixos e agrupar itens paralelos. Ela escreve a página nova como uma lista de blocos. Como esse é o texto da própria IA, ele segue regras:

* ela não pode acrescentar nenhum fato, número, nome, data ou link que não esteja no original;
* o que ela escreve passa pelo limpador de HTML do Moodle, e um link só é mantido se o original o tinha;
* imagens, tabelas e vídeos nunca são escritos pela IA: vêm do original;
* um resultado com número que o original não tem, ou com tamanho distante do original (abaixo de 40% ou acima de 130%), é recusado e **nada é copiado**;
* um texto longo demais para ser enviado inteiro (acima de cerca de 24.000 caracteres) é recusado, em vez de ser cortado.

A proteção barra números que aparecem, não números que somem, então **leia sempre a cópia antes de publicar**. É por isso que a cópia fica oculta.

## Privacidade

O texto da atividade é enviado ao provedor de IA só para planejar (ou, quando você marca a caixa, escrever) os blocos. A página avisa isso antes de você começar. Um curso com as ferramentas de IA desligadas não recebe a opção de estilizar.
