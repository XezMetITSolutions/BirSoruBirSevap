import React, { useEffect, useState } from 'react';
import { View, Text, StyleSheet, FlatList, TouchableOpacity, ActivityIndicator } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { theme } from '../theme';
import { API_ENDPOINTS } from '../api/config';

export const BankSelectionScreen = ({ navigation }: any) => {
  const [banks, setBanks] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchBanks();
  }, []);

  const fetchBanks = async () => {
    try {
      const response = await fetch(API_ENDPOINTS.MOBILE_INFO);
      const data = await response.json();
      setBanks(data.banks);
      setLoading(false);
    } catch (e) {
      console.error(e);
      // Hata durumunda mock data kullanımı (demo için)
      setBanks([
        { id: 'Temel Bilgiler 1', title: 'Temel Bilgiler 1', icon: '📖', count: 6 },
        { id: 'Temel Bilgiler 2', title: 'Temel Bilgiler 2', icon: '📚', count: 6 },
        { id: 'Temel Bilgiler 3', title: 'Temel Bilgiler 3', icon: '🖋️', count: 12 },
        { id: 'İslami İlimler', title: 'İslami İlimler', icon: '🕌', count: 12 },
      ]);
      setLoading(false);
    }
  };

  const renderItem = ({ item }) => (
    <TouchableOpacity 
      style={styles.card}
      onPress={() => navigation.navigate('CategorySelection', { bankId: item.id, bankTitle: item.title })}
    >
      <View style={styles.cardInfo}>
        <Text style={styles.cardTitle}>{item.title}</Text>
        <Text style={styles.cardCount}>{item.count} Konu Mevcut</Text>
      </View>
      <View style={styles.cardIcon}>
        <Text style={styles.iconText}>{item.icon}</Text>
      </View>
    </TouchableOpacity>
  );

  return (
    <SafeAreaView style={styles.container}>
      <View style={styles.header}>
        <TouchableOpacity onPress={() => navigation.goBack()}>
          <Text style={styles.backBtn}>←</Text>
        </TouchableOpacity>
        <Text style={styles.headerTitle}>Soru Bankası</Text>
        <Text style={styles.headerSubtitle}>Başlamak için bir banka seçin</Text>
      </View>

      {loading ? (
        <ActivityIndicator size="large" color={theme.colors.primary} style={styles.content} />
      ) : (
        <FlatList
          data={banks}
          renderItem={renderItem}
          keyExtractor={item => item.id}
          contentContainerStyle={styles.list}
          showsVerticalScrollIndicator={false}
        />
      )}
    </SafeAreaView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.background,
  },
  header: {
    padding: theme.spacing.lg,
    paddingTop: theme.spacing.xl,
  },
  backBtn: {
    fontSize: 24,
    color: theme.colors.text,
    fontFamily: 'Outfit_400Regular',
    marginBottom: theme.spacing.sm,
  },
  headerTitle: {
    fontSize: 28,
    fontWeight: '800',
    color: theme.colors.text,
    fontFamily: 'Outfit_800ExtraBold',
    letterSpacing: -0.5,
  },
  headerSubtitle: {
    fontSize: 16,
    color: theme.colors.textMuted,
    marginTop: 5,
    fontFamily: 'Outfit_400Regular',
  },
  list: {
    padding: theme.spacing.lg,
  },
  card: {
    backgroundColor: theme.colors.card,
    borderRadius: 24,
    padding: 20,
    marginBottom: 16,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  cardInfo: {
    flex: 1,
  },
  cardTitle: {
    fontSize: 18,
    fontWeight: '700',
    color: theme.colors.text,
    fontFamily: 'Outfit_700Bold',
  },
  cardCount: {
    fontSize: 14,
    color: theme.colors.textMuted,
    marginTop: 4,
    fontFamily: 'Outfit_400Regular',
  },
  cardIcon: {
    width: 60,
    height: 60,
    backgroundColor: theme.colors.primary,
    borderRadius: 16,
    alignItems: 'center',
    justifyContent: 'center',
    marginLeft: 16,
  },
  iconText: {
    fontSize: 24,
  },
});
