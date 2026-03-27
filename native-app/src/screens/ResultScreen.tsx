import React from 'react';
import { View, Text, StyleSheet, TouchableOpacity, SafeAreaView, Share, ScrollView } from 'react-native';
import { theme } from '../theme';

export const ResultScreen = ({ route, navigation }) => {
  const { score, total, results, duration } = route.params;

  const onShare = async () => {
    try {
      await Share.share({
        message: `Bir Soru Bir Sevap alıştırmasında ${score} puan aldım! Sen de katıl!`,
      });
    } catch (e) {
      console.error(e);
    }
  };

  const getStatusText = () => {
    if (score >= 80) return 'Mükemmel! 🎉';
    if (score >= 50) return 'Çok İyi! 👍';
    return 'Daha Çok Çalışmalısın! 📚';
  };

  return (
    <SafeAreaView style={styles.container}>
      <ScrollView contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        <View style={styles.scoreBox}>
          <View style={styles.scoreCircle}>
            <Text style={styles.scoreValue}>{score}</Text>
            <Text style={styles.scoreLabel}>PUAN</Text>
          </View>
          <Text style={styles.statusText}>{getStatusText()}</Text>
        </View>

        <View style={styles.statsRow}>
          <View style={styles.statItem}>
            <Text style={styles.statValue}>{results.filter(r => r.isCorrect).length}</Text>
            <Text style={styles.statLabel}>Doğru</Text>
          </View>
          <View style={styles.statItem}>
            <Text style={styles.statValue}>{results.filter(r => !r.isCorrect).length}</Text>
            <Text style={styles.statLabel}>Yanlış</Text>
          </View>
          <View style={styles.statItem}>
            <Text style={styles.statValue}>{Math.floor(duration / 60)}m {duration % 60}s</Text>
            <Text style={styles.statLabel}>Süre</Text>
          </View>
        </View>

        <View style={styles.actions}>
          <TouchableOpacity style={styles.primaryBtn} onPress={() => navigation.navigate('Home')}>
            <Text style={styles.primaryBtnText}>Tekrar Dene</Text>
          </TouchableOpacity>
          <TouchableOpacity style={styles.secondaryBtn} onPress={onShare}>
            <Text style={styles.secondaryBtnText}>Sonucu Paylaş</Text>
          </TouchableOpacity>
        </View>
      </ScrollView>
    </SafeAreaView>
  );
};

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: theme.colors.background },
  content: { padding: theme.spacing.xl, alignItems: 'center' },
  scoreBox: { alignItems: 'center', marginBottom: 40, marginTop: 40 },
  scoreCircle: { width: 180, height: 180, borderRadius: 90, borderWidth: 8, borderColor: theme.colors.primary, alignItems: 'center', justifyContent: 'center', backgroundColor: theme.colors.card },
  scoreValue: { fontSize: 56, fontWeight: '800', color: theme.colors.text, fontFamily: 'Outfit_800ExtraBold' },
  scoreLabel: { fontSize: 14, fontWeight: '600', color: theme.colors.textMuted, letterSpacing: 2, fontFamily: 'Outfit_600SemiBold' },
  statusText: { fontSize: 24, fontWeight: '700', color: theme.colors.text, marginTop: 24, fontFamily: 'Outfit_700Bold' },
  statsRow: { flexDirection: 'row', width: '100%', backgroundColor: theme.colors.card, borderRadius: 24, padding: 24, justifyContent: 'space-between', marginBottom: 40, borderWidth: 1, borderColor: theme.colors.border },
  statItem: { alignItems: 'center', flex: 1 },
  statValue: { fontSize: 20, fontWeight: '700', color: theme.colors.text, marginBottom: 4, fontFamily: 'Outfit_700Bold' },
  statLabel: { fontSize: 12, color: theme.colors.textMuted, fontFamily: 'Outfit_400Regular' },
  actions: { width: '100%', gap: 12 },
  primaryBtn: { backgroundColor: theme.colors.primary, paddingVertical: 18, borderRadius: 20, alignItems: 'center', elevation: 10, shadowColor: theme.colors.primary, shadowOffset: { width: 0, height: 10 }, shadowOpacity: 0.3, shadowRadius: 20 },
  primaryBtnText: { color: 'white', fontSize: 18, fontWeight: '700', fontFamily: 'Outfit_700Bold' },
  secondaryBtn: { backgroundColor: 'transparent', paddingVertical: 18, borderRadius: 20, alignItems: 'center', borderWidth: 1, borderColor: theme.colors.border },
  secondaryBtnText: { color: theme.colors.text, fontSize: 18, fontWeight: '600', fontFamily: 'Outfit_600SemiBold' },
});
