import { parseInlineMarkdown, parseMarkdownBlocks } from '@/utils/markdown';

describe('parseInlineMarkdown', () => {
  it('rend le texte brut tel quel', () => {
    expect(parseInlineMarkdown('Bonjour le monde')).toEqual([
      { type: 'text', content: 'Bonjour le monde' },
    ]);
  });

  it('parse gras, italique, code et liens', () => {
    expect(parseInlineMarkdown('Un **mot** en *plus* de `code`')).toEqual([
      { type: 'text', content: 'Un ' },
      { type: 'bold', content: 'mot' },
      { type: 'text', content: ' en ' },
      { type: 'italic', content: 'plus' },
      { type: 'text', content: ' de ' },
      { type: 'code', content: 'code' },
    ]);
    expect(parseInlineMarkdown('Voir [mon site](https://ex.com) !')).toEqual([
      { type: 'text', content: 'Voir ' },
      { type: 'link', content: 'mon site', url: 'https://ex.com' },
      { type: 'text', content: ' !' },
    ]);
  });

  it("ne casse pas sur du markdown mal formé", () => {
    expect(parseInlineMarkdown('un **gras non fermé')).toEqual([
      { type: 'text', content: 'un **gras non fermé' },
    ]);
  });
});

describe('parseMarkdownBlocks', () => {
  it('sépare titres, paragraphes et listes', () => {
    const blocks = parseMarkdownBlocks(
      '# Titre\n\nUn paragraphe\nsur deux lignes.\n\n- premier\n- second',
    );
    expect(blocks).toHaveLength(3);
    expect(blocks[0]).toMatchObject({ type: 'heading', level: 1 });
    expect(blocks[1]).toMatchObject({
      type: 'paragraph',
      tokens: [{ type: 'text', content: 'Un paragraphe sur deux lignes.' }],
    });
    expect(blocks[2]).toMatchObject({ type: 'list' });
    expect(blocks[2]?.type === 'list' && blocks[2].items).toHaveLength(2);
  });

  it('retourne un paragraphe unique pour du texte simple', () => {
    expect(parseMarkdownBlocks('Simple bio sans markdown')).toEqual([
      {
        type: 'paragraph',
        tokens: [{ type: 'text', content: 'Simple bio sans markdown' }],
      },
    ]);
  });

  it('retourne un tableau vide pour une chaîne vide', () => {
    expect(parseMarkdownBlocks('')).toEqual([]);
    expect(parseMarkdownBlocks('\n\n')).toEqual([]);
  });

  it('tolère une entrée non-string (bio null/undefined venant de l\'API)', () => {
    expect(parseMarkdownBlocks(null as unknown as string)).toEqual([]);
    expect(parseMarkdownBlocks(undefined as unknown as string)).toEqual([]);
    expect(parseMarkdownBlocks(42 as unknown as string)).toEqual([]);
  });
});
