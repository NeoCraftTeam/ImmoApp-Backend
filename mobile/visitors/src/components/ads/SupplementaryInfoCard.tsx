import {
  CalendarDays,
  Droplet,
  FileText,
  Receipt,
  Wallet,
  Zap,
} from '@tamagui/lucide-icons';
import { Linking, Pressable } from 'react-native';
import { Paragraph, XStack, YStack } from 'tamagui';

import { brand } from '@/theme/tokens';
import type { Ad } from '@/types/ad';
import { formatChargeAmount, hasSupplementaryInfo } from '@/utils/supplementary-info';

interface Props {
  ad: Ad;
}

/**
 * « Informations supplémentaires » — port mobile du
 * `SupplementaryInfoCard` web : dépôt de garantie, durée minimum de
 * bail, charges (détail / forfait / eau / électricité / autres) et
 * état des lieux PDF. L'API n'envoie ces champs que si l'annonce est
 * déverrouillée ; verrouillée, la section disparaît (pas de teaser,
 * comme le web).
 */
export function SupplementaryInfoCard({ ad }: Props) {
  if (!ad.is_unlocked || !hasSupplementaryInfo(ad)) {
    return null;
  }

  const hasCharges = Boolean(
    ad.charges_eau ||
      ad.charges_electricite ||
      ad.charges_autres ||
      ad.charges_forfaitaires ||
      ad.detailed_charges,
  );

  return (
    <YStack gap={12}>
      <Paragraph fontSize={17} fontWeight="700" color="$slate900">
        Informations supplémentaires
      </Paragraph>
      <YStack gap={10}>
        {ad.deposit_amount ? (
          <InfoRow
            icon={<Wallet size={16} color="$slate700" />}
            label="Dépôt de garantie"
            value={ad.deposit_amount}
          />
        ) : null}
        {ad.minimum_lease_duration ? (
          <InfoRow
            icon={<CalendarDays size={16} color="$slate700" />}
            label="Durée minimum"
            value={ad.minimum_lease_duration}
          />
        ) : null}

        {hasCharges ? (
          <Paragraph
            fontSize={12}
            fontWeight="700"
            color="$slate500"
            letterSpacing={1.2}
            marginTop={4}
          >
            CHARGES
          </Paragraph>
        ) : null}
        {ad.detailed_charges ? (
          <InfoRow
            icon={<Receipt size={16} color="$slate700" />}
            label="Détail"
            value={ad.detailed_charges}
          />
        ) : null}
        {ad.charges_forfaitaires && ad.charges_montant_forfait ? (
          <InfoRow
            icon={<Receipt size={16} color="$slate700" />}
            label="Forfait mensuel"
            value={formatChargeAmount(ad.charges_montant_forfait)}
          />
        ) : null}
        {ad.charges_eau ? (
          <InfoRow
            icon={<Droplet size={16} color={brand.info} />}
            label="Eau"
            value={formatChargeAmount(ad.charges_eau)}
          />
        ) : null}
        {ad.charges_electricite ? (
          <InfoRow
            icon={<Zap size={16} color={brand.warning} />}
            label="Électricité"
            value={formatChargeAmount(ad.charges_electricite)}
          />
        ) : null}
        {ad.charges_autres ? (
          <InfoRow
            icon={<Receipt size={16} color="$slate700" />}
            label="Autres"
            value={ad.charges_autres}
          />
        ) : null}

        {ad.property_condition_pdf ? (
          <XStack gap={10} alignItems="center">
            <FileText size={16} color={brand.danger} />
            <Paragraph fontSize={14} color="$slate700" flex={1}>
              État des lieux (PDF)
            </Paragraph>
            <Pressable
              onPress={() => void Linking.openURL(ad.property_condition_pdf!)}
              hitSlop={8}
              accessibilityRole="button"
              accessibilityLabel="Voir l'état des lieux (PDF)"
            >
              <Paragraph fontSize={13} fontWeight="700" color={brand.primary}>
                Voir
              </Paragraph>
            </Pressable>
          </XStack>
        ) : null}
      </YStack>
    </YStack>
  );
}

function InfoRow({
  icon,
  label,
  value,
}: {
  icon: React.ReactNode;
  label: string;
  value: string;
}) {
  return (
    <XStack gap={10} alignItems="flex-start">
      {icon}
      <YStack flex={1} gap={2}>
        <Paragraph fontSize={12.5} color="$slate500" fontWeight="600">
          {label}
        </Paragraph>
        <Paragraph fontSize={14} color="$slate900" lineHeight={20}>
          {value}
        </Paragraph>
      </YStack>
    </XStack>
  );
}
