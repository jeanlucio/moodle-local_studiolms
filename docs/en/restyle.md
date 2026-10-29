# 🖌️ Restyle what you already wrote

Teachers often have the text already: pasted from a document or a web page, with no real headings. **Restyle** gives it the structure of the Studio blocks without rewriting it.

## How it works

1. On the landing choose **Restyle existing**, or open a page and choose **Restyle with StudioLMS** in its settings tabs. The list shows the pages and labels of the course, by section.
2. Click **Restyle** on one of them.
3. The activity is **duplicated**. The original is never touched: the new layout goes to a **hidden copy** right after it, named "Title (StudioLMS)".
4. Open the copy to adjust it in the editor, view it, and publish it (or delete it) when you are happy.

Only **pages and labels** can be restyled, and only when their text is HTML. It needs the `local/studiolms:restyle` capability, and the Moodle capabilities to back up, restore and add that kind of activity.

## What the AI does, and what it does not

The text is cut into lines (a paragraph typed with line breaks becomes one piece per line, so a title written as a line of a paragraph can be found). The AI sees all of it, numbered, and answers **only roles and groups of line numbers**: a title, a list, a callout, a card, a grid of parallel items, steps, or a folded section. The words of each block are the original ones, put there by the plugin. **No word is lost or moved**: a test checks that every word of the original is in the result, in the same order.

Pictures, videos, tables and Studio blocks that are already in the text are carried over exactly as they are. A line that ends with a colon, or that is a full sentence, is never made a title.

The result is made by an AI, so two runs on the same text may differ a little. Restyling the same page again simply makes another copy.

## Reorganise and polish the text (optional)

A checkbox on the list, **off by default**, lets the AI go further: find titles where the text has none, remove repetition, split long paragraphs, shorten wordy ones and group parallel items. It writes the new page as a list of blocks. Because that is the AI's own text, it is held to rules:

* it must not add any fact, number, name, date or link that is not in the original;
* what it writes goes through Moodle's HTML cleaner, and a link is kept only if the original had it;
* pictures, tables and videos are never written by the AI: they come from the original;
* a result with a number the original does not have, or a length far from the original (under 40% or over 130%), is refused and **nothing is copied**;
* a text too long to be sent whole (over about 24,000 characters) is refused, instead of being cut.

The guard stops numbers that appear, not numbers that disappear, so **always read the copy before publishing it**. That is why the copy is hidden.

## Privacy

The text of the activity is sent to the AI provider only to plan (or, when you tick the box, to write) the blocks. The page says so before you start. A course with the AI tools turned off is not offered the restyle.
