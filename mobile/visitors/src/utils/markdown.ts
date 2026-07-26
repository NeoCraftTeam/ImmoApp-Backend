/**
 * Minimal markdown parser for the subset the web `MarkdownBioEditor`
 * produces (bailleur bios): **bold**, *italic*, `code`, [links](url),
 * `#`/`##`/`###` headings and `-`/`*` bullet lists. Kept dependency-free
 * on purpose — anything unrecognised degrades to plain text.
 */

export type InlineToken =
  | { type: 'text'; content: string }
  | { type: 'bold'; content: string }
  | { type: 'italic'; content: string }
  | { type: 'code'; content: string }
  | { type: 'link'; content: string; url: string };

export type MarkdownBlock =
  | { type: 'heading'; level: 1 | 2 | 3; tokens: InlineToken[] }
  | { type: 'list'; items: InlineToken[][] }
  | { type: 'paragraph'; tokens: InlineToken[] };

const INLINE_PATTERN =
  /\*\*([^*]+)\*\*|\*([^*]+)\*|`([^`]+)`|\[([^\]]+)\]\(([^)\s]+)\)/g;

export function parseInlineMarkdown(text: string): InlineToken[] {
  const tokens: InlineToken[] = [];
  let lastIndex = 0;
  for (const match of text.matchAll(INLINE_PATTERN)) {
    const index = match.index ?? 0;
    if (index > lastIndex) {
      tokens.push({ type: 'text', content: text.slice(lastIndex, index) });
    }
    const [, bold, italic, code, linkText, linkUrl] = match;
    if (bold != null) {
      tokens.push({ type: 'bold', content: bold });
    } else if (italic != null) {
      tokens.push({ type: 'italic', content: italic });
    } else if (code != null) {
      tokens.push({ type: 'code', content: code });
    } else if (linkText != null && linkUrl != null) {
      tokens.push({ type: 'link', content: linkText, url: linkUrl });
    }
    lastIndex = index + match[0].length;
  }
  if (lastIndex < text.length) {
    tokens.push({ type: 'text', content: text.slice(lastIndex) });
  }
  return tokens;
}

export function parseMarkdownBlocks(markdown: string): MarkdownBlock[] {
  // Défensif : l'entrée vient d'un champ API (bio) qui peut arriver
  // `null`/`undefined` malgré le typage — évite un crash `.split of null`.
  if (typeof markdown !== 'string' || markdown === '') {
    return [];
  }

  const blocks: MarkdownBlock[] = [];
  let paragraphLines: string[] = [];
  let listItems: InlineToken[][] = [];

  const flushParagraph = () => {
    if (paragraphLines.length > 0) {
      blocks.push({
        type: 'paragraph',
        tokens: parseInlineMarkdown(paragraphLines.join(' ')),
      });
      paragraphLines = [];
    }
  };
  const flushList = () => {
    if (listItems.length > 0) {
      blocks.push({ type: 'list', items: listItems });
      listItems = [];
    }
  };

  for (const rawLine of markdown.split('\n')) {
    const line = rawLine.trim();
    if (line === '') {
      flushParagraph();
      flushList();
      continue;
    }

    const heading = /^(#{1,3})\s+(.*)$/.exec(line);
    if (heading?.[1] != null && heading[2] != null) {
      flushParagraph();
      flushList();
      blocks.push({
        type: 'heading',
        level: heading[1].length as 1 | 2 | 3,
        tokens: parseInlineMarkdown(heading[2]),
      });
      continue;
    }

    const listItem = /^[-*]\s+(.*)$/.exec(line);
    if (listItem?.[1] != null) {
      flushParagraph();
      listItems.push(parseInlineMarkdown(listItem[1]));
      continue;
    }

    flushList();
    paragraphLines.push(line);
  }
  flushParagraph();
  flushList();
  return blocks;
}
