import { Linking } from 'react-native';
import { Paragraph, XStack, YStack } from 'tamagui';

import { brand } from '@/theme/tokens';
import {
  parseMarkdownBlocks,
  type InlineToken,
  type MarkdownBlock,
} from '@/utils/markdown';

interface Props {
  children: string;
  /** Base body font size (headings scale up from it). */
  fontSize?: number;
  color?: string;
}

/**
 * Renders the markdown subset produced by the web bio editor
 * (bold / italic / code / links / headings / bullet lists) with plain
 * Tamagui text — the mobile counterpart of `<ReactMarkdown>` on the
 * bailleur public profile.
 */
export function MarkdownText({
  children,
  fontSize = 14,
  color = '$slate700',
}: Props) {
  const blocks = parseMarkdownBlocks(children);
  if (blocks.length === 0) {
    return null;
  }

  return (
    <YStack gap={8}>
      {blocks.map((block, index) => (
        <MarkdownBlockView
          key={index}
          block={block}
          fontSize={fontSize}
          color={color}
        />
      ))}
    </YStack>
  );
}

function MarkdownBlockView({
  block,
  fontSize,
  color,
}: {
  block: MarkdownBlock;
  fontSize: number;
  color: string;
}) {
  if (block.type === 'heading') {
    const headingSize = fontSize + (4 - block.level);
    return (
      <Paragraph fontSize={headingSize} fontWeight="700" color="$slate900">
        <InlineTokens tokens={block.tokens} fontSize={headingSize} color="$slate900" />
      </Paragraph>
    );
  }

  if (block.type === 'list') {
    return (
      <YStack gap={4}>
        {block.items.map((tokens, index) => (
          <XStack key={index} gap={8} alignItems="flex-start">
            <Paragraph fontSize={fontSize} color={color} lineHeight={22}>
              •
            </Paragraph>
            <Paragraph flex={1} fontSize={fontSize} color={color} lineHeight={22}>
              <InlineTokens tokens={tokens} fontSize={fontSize} color={color} />
            </Paragraph>
          </XStack>
        ))}
      </YStack>
    );
  }

  return (
    <Paragraph fontSize={fontSize} color={color} lineHeight={22}>
      <InlineTokens tokens={block.tokens} fontSize={fontSize} color={color} />
    </Paragraph>
  );
}

function InlineTokens({
  tokens,
  fontSize,
  color,
}: {
  tokens: InlineToken[];
  fontSize: number;
  color: string;
}) {
  return (
    <>
      {tokens.map((token, index) => {
        switch (token.type) {
          case 'bold':
            return (
              <Paragraph key={index} fontSize={fontSize} fontWeight="700" color={color}>
                {token.content}
              </Paragraph>
            );
          case 'italic':
            return (
              <Paragraph key={index} fontSize={fontSize} fontStyle="italic" color={color}>
                {token.content}
              </Paragraph>
            );
          case 'code':
            return (
              <Paragraph
                key={index}
                fontSize={fontSize - 1}
                fontFamily="$mono"
                color="$slate900"
                backgroundColor="$slate100"
              >
                {token.content}
              </Paragraph>
            );
          case 'link':
            return (
              <Paragraph
                key={index}
                fontSize={fontSize}
                color={brand.primary}
                textDecorationLine="underline"
                onPress={() => void Linking.openURL(token.url)}
                accessibilityRole="link"
              >
                {token.content}
              </Paragraph>
            );
          case 'text':
          default:
            return token.content;
        }
      })}
    </>
  );
}
