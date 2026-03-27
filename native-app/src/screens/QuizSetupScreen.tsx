import React, { useState } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, Switch } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { theme } from '../theme';

export const QuizSetupScreen = ({ route, navigation }) => {
  const { bankId, category } = route.params;
  const [count, setCount] = useState(10);
  const [timer, setTimer] = useState(false);

  const counts = [5, 10, 20, 30, 50];

  return (
    <SafeAreaView style={styles.container}>
      <View style={styles.header}>
        <TouchableOpacity onPress={() => navigation.goBack()}>
          <Text style={styles.backBtn}>←</Text>
        </TouchableOpacity>
        <Text style={styles.headerTitle}>{category}</Text>
        <Text style={styles.headerSubtitle}>Hazır mısınız?</Text>
      </View>

      <View style={styles.content}>
        <View style={styles.glassCard}>
          <Text style={styles.label}>Soru Sayısı</Text>
          <View style={styles.optionGrid}>
            {counts.map(c => (
              <TouchableOpacity 
                key={c}
                style={[styles.countOption, count === c && styles.countSelected]}
                onPress={() => setCount(c)}
              >
                <Text style={[styles.countText, count === c && styles.countTextSelected]}>{c}</Text>
              </TouchableOpacity>
            ))}
          </View>

          <View style={styles.divider} />

          <View style={styles.row}>
            <Text style={styles.label}>Zamanlayıcı</Text>
            <View style={styles.toggleRow}>
              <Text style={styles.toggleText}>{timer ? 'Süreli (30s)' : 'Sınırsız'}</Text>
              <Switch 
                value={timer}
                onValueChange={setTimer}
                trackColor={{ false: theme.colors.card, true: theme.colors.primary }}
                thumbColor={'#fff'}
              />
            </View>
          </View>
        </View>

        <TouchableOpacity 
          style={styles.startBtn}
          onPress={() => navigation.navigate('Quiz', { bankId, category, count, timer })}
        >
          <Text style={styles.startBtnText}>Alıştırmaya Başla</Text>
        </TouchableOpacity>
      </View>
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
  content: {
    flex: 1,
    padding: theme.spacing.lg,
  },
  glassCard: {
    backgroundColor: theme.colors.card,
    borderRadius: 30,
    padding: 30,
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  label: {
    fontSize: 14,
    fontWeight: '700',
    color: theme.colors.textMuted,
    textTransform: 'uppercase',
    marginBottom: 20,
    fontFamily: 'Outfit_700Bold',
  },
  optionGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 12,
  },
  countOption: {
    paddingHorizontal: 16,
    paddingVertical: 12,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: theme.colors.border,
    minWidth: 50,
    alignItems: 'center',
    justifyContent: 'center',
  },
  countSelected: {
    backgroundColor: theme.colors.primary,
    borderColor: theme.colors.primary,
  },
  countText: {
    fontSize: 16,
    color: theme.colors.text,
    fontFamily: 'Outfit_600SemiBold',
  },
  countTextSelected: {
    color: 'white',
  },
  divider: {
    height: 1,
    backgroundColor: theme.colors.border,
    marginVertical: 30,
  },
  row: {
    flexDirection: 'column',
  },
  toggleRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    backgroundColor: 'rgba(255,255,255,0.05)',
    padding: 16,
    borderRadius: 16,
  },
  toggleText: {
    color: theme.colors.text,
    fontSize: 16,
    fontFamily: 'Outfit_600SemiBold',
  },
  startBtn: {
    backgroundColor: theme.colors.primary,
    paddingVertical: 20,
    borderRadius: 24,
    marginTop: 30,
    width: '100%',
    alignItems: 'center',
    elevation: 10,
    shadowColor: theme.colors.primary,
    shadowOffset: { width: 0, height: 10 },
    shadowOpacity: 0.3,
    shadowRadius: 20,
  },
  startBtnText: {
    color: 'white',
    fontSize: 18,
    fontWeight: '700',
    fontFamily: 'Outfit_700Bold',
  },
});
