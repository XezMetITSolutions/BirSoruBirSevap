import React, { useEffect, useState } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, ScrollView, Animated, Dimensions } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { theme } from '../theme';
import { authStorage } from '../api/auth';
import { API_ENDPOINTS } from '../api/config';
import { Ionicons } from '@expo/vector-icons';

const { width } = Dimensions.get('window');

export const DashboardScreen = ({ navigation }: any) => {
  const [user, setUser] = useState<any>(null);
  const [stats, setStats] = useState({ score: 0, badges: 0, practiceCount: 0 });
  const fadeAnim = useState(new Animated.Value(0))[0];

  useEffect(() => {
    loadData();
    Animated.timing(fadeAnim, {
      toValue: 1,
      duration: 800,
      useNativeDriver: true,
    }).start();
  }, []);

  const loadData = async () => {
    const userData = await authStorage.getUser();
    setUser(userData);
    if (userData?.username) {
      fetchStats(userData.username);
    }
  };

  const fetchStats = async (username: string) => {
    try {
      const response = await fetch(`${API_ENDPOINTS.STUDENT_STATS}?username=${username}`);
      const data = await response.json();
      if (data.success) {
        setStats(data.stats);
      }
    } catch (error) {
      console.error('Fetch stats error:', error);
    }
  };

  const handleLogout = async () => {
    await authStorage.logout();
    navigation.replace('Login');
  };

  const StatBox = ({ title, value, color }: any) => (
    <View style={[styles.statBox, { borderLeftColor: color }]}>
      <Text style={styles.statLabel}>{title}</Text>
      <Text style={styles.statValue}>{value}</Text>
    </View>
  );

  return (
    <SafeAreaView style={styles.container}>
      <ScrollView contentContainerStyle={styles.scrollContent}>
        <View style={styles.header}>
          <View>
            <Text style={styles.welcomeText}>Hoş Geldin,</Text>
            <Text style={styles.userName}>{user?.full_name || 'Öğrenci'}</Text>
          </View>
          <TouchableOpacity style={styles.logoutBtn} onPress={handleLogout}>
            <Ionicons name="log-out-outline" color={theme.colors.error} size={22} />
          </TouchableOpacity>
        </View>

        <Animated.View style={{ opacity: fadeAnim }}>
          {/* Quick Stats */}
          <View style={styles.statsRow}>
            <StatBox title="Puan" value={stats.score.toLocaleString()} color={theme.colors.primary} />
            <StatBox title="Rozet" value={stats.badges} color={theme.colors.secondary} />
          </View>

          {/* Main Actions */}
          <Text style={styles.sectionTitle}>Neler Yapabilirsin?</Text>
          
          <TouchableOpacity 
            style={styles.actionCard}
            onPress={() => navigation.navigate('BankSelection')}
          >
            <View style={[styles.actionIcon, { backgroundColor: theme.colors.primary + '20' }]}>
              <Ionicons name="grid-outline" color={theme.colors.primary} size={28} />
            </View>
            <View style={styles.actionInfo}>
              <Text style={styles.actionTitle}>Alıştırma Yap</Text>
              <Text style={styles.actionDesc}>Banka ve kategorilere göre soru çöz.</Text>
            </View>
            <Ionicons name="chevron-forward" color={theme.colors.textMuted} size={20} />
          </TouchableOpacity>

          <TouchableOpacity 
            style={styles.actionCard}
            onPress={() => navigation.navigate('Exams')}
          >
            <View style={[styles.actionIcon, { backgroundColor: theme.colors.secondary + '20' }]}>
              <Ionicons name="list-outline" color={theme.colors.secondary} size={28} />
            </View>
            <View style={styles.actionInfo}>
              <Text style={styles.actionTitle}>Sınava Gir</Text>
              <Text style={styles.actionDesc}>Öğretmenin atadığı sınavlara katıl.</Text>
            </View>
            <Ionicons name="chevron-forward" color={theme.colors.textMuted} size={20} />
          </TouchableOpacity>

          <TouchableOpacity 
            style={styles.actionCard}
            onPress={() => navigation.navigate('Badges')}
          >
            <View style={[styles.actionIcon, { backgroundColor: '#FFD70020' }]}>
              <Ionicons name="trophy-outline" color="#FFD700" size={28} />
            </View>
            <View style={styles.actionInfo}>
              <Text style={styles.actionTitle}>Rozetlerim</Text>
              <Text style={styles.actionDesc}>Kazandığın tüm başarıları incele.</Text>
            </View>
            <Ionicons name="chevron-forward" color={theme.colors.textMuted} size={20} />
          </TouchableOpacity>

        </Animated.View>
      </ScrollView>
    </SafeAreaView>
  );
};

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: theme.colors.background },
  scrollContent: { padding: 20 },
  header: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 30, marginTop: 10 },
  welcomeText: { color: theme.colors.textMuted, fontSize: 16, fontFamily: 'Outfit_400Regular' },
  userName: { color: theme.colors.text, fontSize: 24, fontWeight: '800', fontFamily: 'Outfit_800ExtraBold' },
  logoutBtn: { backgroundColor: theme.colors.card, padding: 12, borderRadius: 15, borderWidth: 1, borderColor: theme.colors.border },
  statsRow: { flexDirection: 'row', gap: 15, marginBottom: 30 },
  statBox: { flex: 1, backgroundColor: theme.colors.card, padding: 20, borderRadius: 24, borderLeftWidth: 4, borderWidth: 1, borderColor: theme.colors.border },
  statLabel: { color: theme.colors.textMuted, fontSize: 12, marginBottom: 4, fontFamily: 'Outfit_600SemiBold' },
  statValue: { color: theme.colors.text, fontSize: 22, fontWeight: '800', fontFamily: 'Outfit_800ExtraBold' },
  sectionTitle: { color: theme.colors.text, fontSize: 18, fontWeight: '700', marginBottom: 15, fontFamily: 'Outfit_700Bold' },
  actionCard: { backgroundColor: theme.colors.card, borderRadius: 24, padding: 18, marginBottom: 15, flexDirection: 'row', alignItems: 'center', borderWidth: 1, borderColor: theme.colors.border },
  actionIcon: { width: 56, height: 56, borderRadius: 18, alignItems: 'center', justifyContent: 'center', marginRight: 16 },
  actionInfo: { flex: 1 },
  actionTitle: { color: theme.colors.text, fontSize: 18, fontWeight: '700', marginBottom: 2, fontFamily: 'Outfit_700Bold' },
  actionDesc: { color: theme.colors.textMuted, fontSize: 13, fontFamily: 'Outfit_400Regular' },
});
