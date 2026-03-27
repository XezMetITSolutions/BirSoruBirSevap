import React, { useState, useEffect } from 'react';
import { 
  View, 
  Text, 
  StyleSheet, 
  FlatList, 
  TouchableOpacity, 
  ActivityIndicator,
  RefreshControl
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { theme } from '../theme';
import { API_ENDPOINTS } from '../api/config';
import { authStorage } from '../api/auth';

export const ResultsScreen = ({ navigation }: any) => {
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [results, setResults] = useState<any[]>([]);

  useEffect(() => {
    fetchHistory();
  }, []);

  const fetchHistory = async () => {
    try {
      const user = await authStorage.getUser();
      if (!user?.username) return;

      const response = await fetch(`${API_ENDPOINTS.STUDENT_HISTORY}?username=${user.username}`);
      const data = await response.json();
      
      if (data.success) {
        setResults(data.results);
      }
    } catch (error) {
      console.error('Fetch history error:', error);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  const onRefresh = () => {
    setRefreshing(true);
    fetchHistory();
  };

  const formatDate = (dateStr: string) => {
    const d = new Date(dateStr);
    return d.toLocaleDateString('tr-TR', { 
      day: 'numeric', 
      month: 'long', 
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  };

  const renderResultItem = ({ item }: any) => (
    <View style={styles.card}>
      <View style={[styles.typeBadge, { 
        backgroundColor: item.type === 'exam' ? theme.colors.error + '15' : theme.colors.primary + '15'
      }]}>
        <Ionicons 
          name={item.type === 'exam' ? 'school' : 'book'} 
          size={16} 
          color={item.type === 'exam' ? theme.colors.error : theme.colors.primary} 
        />
        <Text style={[styles.typeText, { 
          color: item.type === 'exam' ? theme.colors.error : theme.colors.primary 
        }]}>
          {item.type === 'exam' ? 'Sınav' : 'Alıştırma'}
        </Text>
      </View>

      <View style={styles.cardHeader}>
        <View style={styles.titleArea}>
          <Text style={styles.bankName}>{item.bank}</Text>
          <Text style={styles.categoryName}>{item.category}</Text>
        </View>
        <View style={styles.scoreArea}>
          <Text style={[styles.scoreText, { 
            color: item.score >= 70 ? theme.colors.success : theme.colors.error 
          }]}>
            {item.score}%
          </Text>
        </View>
      </View>

      <View style={styles.divider} />

      <View style={styles.cardFooter}>
        <View style={styles.footerItem}>
          <Ionicons name="time-outline" size={14} color={theme.colors.textMuted} />
          <Text style={styles.footerText}>{Math.floor(item.duration / 60)} dk {item.duration % 60} sn</Text>
        </View>
        <Text style={styles.dateText}>{formatDate(item.created_at)}</Text>
      </View>
    </View>
  );

  return (
    <SafeAreaView style={styles.container}>
      <View style={styles.header}>
        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backBtn}>
          <Ionicons name="arrow-back" size={24} color={theme.colors.text} />
        </TouchableOpacity>
        <Text style={styles.headerTitle}>Sonuçlarım</Text>
      </View>

      {loading ? (
        <View style={styles.centered}>
          <ActivityIndicator size="large" color={theme.colors.primary} />
        </View>
      ) : results.length === 0 ? (
        <View style={styles.emptyState}>
          <Ionicons name="stats-chart-outline" size={80} color={theme.colors.border} />
          <Text style={styles.emptyTitle}>Henüz Bir Kayıt Yok</Text>
          <Text style={styles.emptyDesc}>Çözdüğün alıştırmalar ve katıldığın sınavlar burada listelenir.</Text>
          <TouchableOpacity 
            style={styles.startBtn}
            onPress={() => navigation.navigate('BankSelection')}
          >
            <Text style={styles.startBtnText}>Hemen Başla</Text>
          </TouchableOpacity>
        </View>
      ) : (
        <FlatList
          data={results}
          renderItem={renderResultItem}
          keyExtractor={(item, index) => index.toString()}
          contentContainerStyle={styles.listContent}
          refreshControl={
            <RefreshControl refreshing={refreshing} onRefresh={onRefresh} colors={[theme.colors.primary]} />
          }
        />
      )}
    </SafeAreaView>
  );
};

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: theme.colors.background },
  header: { 
    flexDirection: 'row', 
    alignItems: 'center', 
    padding: theme.spacing.lg,
    borderBottomWidth: 1,
    borderBottomColor: theme.colors.border
  },
  backBtn: { padding: 4, marginRight: 12 },
  headerTitle: { fontSize: 22, fontWeight: '800', color: theme.colors.text, fontFamily: 'Outfit_800ExtraBold' },
  centered: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  listContent: { padding: theme.spacing.lg, paddingBottom: 40 },
  card: { 
    backgroundColor: theme.colors.card, 
    borderRadius: 24, 
    padding: 20, 
    marginBottom: 16,
    borderWidth: 1,
    borderColor: theme.colors.border,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.05,
    shadowRadius: 10,
    elevation: 3
  },
  typeBadge: { 
    flexDirection: 'row', 
    alignItems: 'center', 
    alignSelf: 'flex-start',
    paddingHorizontal: 10, 
    paddingVertical: 5, 
    borderRadius: 10,
    marginBottom: 12,
    gap: 6
  },
  typeText: { fontSize: 11, fontWeight: '800', textTransform: 'uppercase', letterSpacing: 0.5, fontFamily: 'Outfit_700Bold' },
  cardHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start' },
  titleArea: { flex: 1, marginRight: 10 },
  bankName: { fontSize: 13, color: theme.colors.primary, fontWeight: '700', marginBottom: 2, fontFamily: 'Outfit_700Bold' },
  categoryName: { fontSize: 17, fontWeight: '700', color: theme.colors.text, fontFamily: 'Outfit_700Bold' },
  scoreArea: { alignItems: 'flex-end' },
  scoreText: { fontSize: 24, fontWeight: '900', fontFamily: 'Outfit_900Black' },
  divider: { height: 1.5, backgroundColor: theme.colors.border, marginVertical: 15, borderRadius: 1 },
  cardFooter: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  footerItem: { flexDirection: 'row', alignItems: 'center', gap: 6 },
  footerText: { fontSize: 12, color: theme.colors.textMuted, fontFamily: 'Outfit_500Medium' },
  dateText: { fontSize: 11, color: theme.colors.textMuted, fontFamily: 'Outfit_400Regular' },
  emptyState: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: 40 },
  emptyTitle: { fontSize: 20, fontWeight: '800', color: theme.colors.text, marginTop: 24, marginBottom: 8, fontFamily: 'Outfit_800ExtraBold' },
  emptyDesc: { fontSize: 14, color: theme.colors.textMuted, textAlign: 'center', marginBottom: 30, fontFamily: 'Outfit_400Regular', lineHeight: 20 },
  startBtn: { backgroundColor: theme.colors.primary, paddingHorizontal: 30, paddingVertical: 15, borderRadius: 16, elevation: 5 },
  startBtnText: { color: 'white', fontWeight: '800', fontSize: 16, fontFamily: 'Outfit_700Bold' }
});
