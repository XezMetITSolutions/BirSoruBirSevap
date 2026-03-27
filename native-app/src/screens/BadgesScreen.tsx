import React, { useEffect, useState } from 'react';
import { View, Text, StyleSheet, FlatList, ActivityIndicator, TouchableOpacity, Animated } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { theme } from '../theme';
import { API_ENDPOINTS } from '../api/config';
import { authStorage } from '../api/auth';
import { Ionicons } from '@expo/vector-icons';

const getBadgeIcon = (iconName: string) => {
  switch (iconName) {
    case 'trophy': return 'trophy-outline';
    case 'award': return 'ribbon-outline';
    case 'star': return 'star-outline';
    case 'zap': return 'flash-outline';
    case 'target': return 'md-target';
    case 'book': return 'book-outline';
    case 'clock': return 'time-outline';
    default: return 'trophy-outline';
  }
};

export const BadgesScreen = ({ navigation }: any) => {
  const [badges, setBadges] = useState([]);
  const [loading, setLoading] = useState(true);
  const [user, setUser] = useState(null);

  useEffect(() => {
    loadData();
  }, []);

  const loadData = async () => {
    const userData = await authStorage.getUser();
    if (!userData) {
      setLoading(false);
      return;
    }
    setUser(userData);

    try {
      const response = await fetch(`${API_ENDPOINTS.BADGES}?username=${userData.username}`);
      const data = await response.json();
      if (data.success) {
        setBadges(data.badges);
      }
    } catch (e) {
      console.error(e);
    } finally {
      setLoading(false);
    }
  };

  const renderBadge = ({ item }: any) => {
    const levels = ['Bronz', 'Gümüş', 'Altın', 'Platin'];
    const currentLevelName = levels[item.level - 1] || levels[0];

    // Define badgeColors and isLocked based on existing logic or reasonable defaults
    const badgeColors: { [key: string]: string } = {
      Bronz: '#CD7F32',
      Gümüş: '#C0C0C0',
      Altın: '#FFD700',
      Platin: '#E5E4E2',
    };
    const isLocked = item.level === 0; // Assuming level 0 means locked

    return (
      <View style={styles.badgeCard}>
        <View style={[styles.iconContainer, { backgroundColor: isLocked ? theme.colors.border : (badgeColors[currentLevelName] || badgeColors.Bronz) + '20' }]}>
          <Ionicons 
            name={getBadgeIcon(item.icon) as any} 
            size={32} 
            color={isLocked ? theme.colors.textMuted : (badgeColors[currentLevelName] || badgeColors.Bronz)} 
          />
        </View>
        <View style={styles.badgeInfo}>
          <Text style={styles.badgeName}>{item.name}</Text>
          <Text style={styles.badgeLevel}>{currentLevelName} Seviye</Text>
          <View style={styles.progressBarBg}>
            <View style={[styles.progressBarFill, { width: `${(item.level / 3) * 100}%` }]} />
          </View>
        </View>
      </View>
    );
  };

  if (loading) return <View style={styles.centered}><ActivityIndicator color={theme.colors.primary} /></View>;

  if (!user) {
    return (
      <SafeAreaView style={styles.container}>
        <View style={styles.centered}>
          <Text style={styles.msg}>Rozetlerini görmek için giriş yapmalısın.</Text>
          <TouchableOpacity style={styles.loginBtn} onPress={() => navigation.navigate('Login')}>
            <Text style={styles.loginText}>Giriş Yap</Text>
          </TouchableOpacity>
        </View>
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.container}>
      <View style={styles.header}>
        <TouchableOpacity style={styles.backBtn} onPress={() => navigation.goBack()}>
          <Text style={styles.backText}>←</Text>
        </TouchableOpacity>
        <Text style={styles.title}>Başarılarım</Text>
      </View>

      <FlatList
        data={badges}
        keyExtractor={(item) => item.key}
        renderItem={renderBadge}
        contentContainerStyle={styles.list}
        ListEmptyComponent={
          <View style={styles.emptyContainer}>
            <Ionicons name="trophy-outline" size={64} color={theme.colors.textMuted} />
            <Text style={styles.emptyText}>Henüz rozetin yok. Alıştırma çözmeye devam et!</Text>
          </View>
        }
      />
    </SafeAreaView>
  );
};

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: theme.colors.background },
  centered: { flex: 1, justifyContent: 'center', alignItems: 'center', padding: 20 },
  header: { padding: 20, flexDirection: 'row', alignItems: 'center' },
  backBtn: { marginRight: 15 },
  backText: { color: theme.colors.text, fontSize: 32 },
  title: { fontSize: 24, fontWeight: '800', color: theme.colors.text, fontFamily: 'Outfit_800ExtraBold' },
  list: { padding: 20 },
  badgeCard: { backgroundColor: theme.colors.card, borderRadius: 24, padding: 20, marginBottom: 16, flexDirection: 'row', alignItems: 'center', borderWidth: 1, borderColor: theme.colors.border },
  iconContainer: { width: 64, height: 64, borderRadius: 20, alignItems: 'center', justifyContent: 'center', marginRight: 20 },
  badgeInfo: { flex: 1 },
  badgeName: { fontSize: 18, fontWeight: '700', color: theme.colors.text, marginBottom: 4, fontFamily: 'Outfit_700Bold' },
  badgeLevel: { fontSize: 14, color: theme.colors.textMuted, marginBottom: 10, fontFamily: 'Outfit_400Regular' },
  progressBarBg: { height: 6, backgroundColor: 'rgba(255,255,255,0.05)', borderRadius: 3, overflow: 'hidden' },
  progressBarFill: { height: '100%', backgroundColor: theme.colors.primary, borderRadius: 3 },
  emptyContainer: { alignItems: 'center', marginTop: 100 },
  emptyText: { color: theme.colors.textMuted, textAlign: 'center', marginTop: 20, fontSize: 16, fontFamily: 'Outfit_400Regular' },
  msg: { color: theme.colors.text, marginBottom: 20, textAlign: 'center' },
  loginBtn: { backgroundColor: theme.colors.primary, paddingHorizontal: 30, paddingVertical: 15, borderRadius: 15 },
  loginText: { color: 'white', fontWeight: 'bold' },
});
