import React, { useState, useEffect } from 'react';
import { 
  View, 
  Text, 
  StyleSheet, 
  FlatList, 
  TouchableOpacity, 
  ActivityIndicator, 
  TextInput,
  Alert
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { theme } from '../theme';
import { API_ENDPOINTS, BASE_URL } from '../api/config';
import AsyncStorage from '@react-native-async-storage/async-storage';

export default function ExamsScreen({ navigation }: any) {
  const [loading, setLoading] = useState(true);
  const [exams, setExams] = useState<any[]>([]);
  const [examCode, setExamCode] = useState('');
  const [joining, setJoining] = useState(false);

  useEffect(() => {
    fetchExams();
  }, []);

  const fetchExams = async () => {
    try {
      const userDataStr = await AsyncStorage.getItem('user_data');
      if (!userDataStr) return;
      const userData = JSON.parse(userDataStr);
      
      const response = await fetch(`${BASE_URL}api_student_exams.php?username=${userData.username}`);
      const data = await response.json();
      
      if (data.success) {
        setExams(data.exams);
      }
    } catch (error) {
      console.error('Fetch exams error:', error);
    } finally {
      setLoading(false);
    }
  };

  const joinExam = async (code: string) => {
    if (!code) return;
    setJoining(true);
    try {
      const userDataStr = await AsyncStorage.getItem('user_data');
      const userData = JSON.parse(userDataStr || '{}');
      
      const response = await fetch(`${BASE_URL}api_exam_join.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          exam_code: code,
          username: userData.username
        })
      });
      
      const data = await response.json();
      
      if (data.success) {
        navigation.navigate('Quiz', { 
          mode: 'exam',
          examId: code,
          questions: data.questions,
          title: data.title,
          duration: data.duration
        });
      } else {
        Alert.alert('Hata', data.error || 'Sınava girilemedi.');
      }
    } catch (error) {
      Alert.alert('Hata', 'Sunucuya bağlanılamadı.');
    } finally {
      setJoining(false);
    }
  };

  const renderExamItem = ({ item }: any) => (
    <TouchableOpacity 
      style={styles.examCard}
      onPress={() => joinExam(item.exam_id)}
    >
      <View style={styles.examIcon}>
        <Ionicons name="document-text" size={24} color={theme.colors.primary} />
      </View>
      <View style={styles.examInfo}>
        <Text style={styles.examTitle}>{item.title}</Text>
        <Text style={styles.examMeta}>
          {item.duration} dk • {item.questionCount} Soru
        </Text>
      </View>
      <Ionicons name="chevron-forward" size={20} color={theme.colors.textMuted} />
    </TouchableOpacity>
  );

  return (
    <SafeAreaView style={styles.container}>
      <View style={styles.header}>
        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backBtn}>
          <Ionicons name="arrow-back" size={24} color={theme.colors.text} />
        </TouchableOpacity>
        <Text style={styles.headerTitle}>Sınavlarım</Text>
      </View>

      <View style={styles.joinSection}>
        <Text style={styles.sectionTitle}>Sınav Kodu ile Giriş</Text>
        <View style={styles.inputRow}>
          <TextInput
            style={styles.input}
            placeholder="Örn: A1B2C3D4"
            value={examCode}
            onChangeText={setExamCode}
            autoCapitalize="characters"
          />
          <TouchableOpacity 
            style={[styles.joinBtn, (!examCode || joining) && styles.disabledBtn]}
            onPress={() => joinExam(examCode)}
            disabled={!examCode || joining}
          >
            {joining ? (
              <ActivityIndicator color="white" />
            ) : (
              <Text style={styles.joinBtnText}>Giriş</Text>
            )}
          </TouchableOpacity>
        </View>
      </View>

      <View style={styles.listSection}>
        <Text style={styles.sectionTitle}>Aktif Sınavlar</Text>
        {loading ? (
          <ActivityIndicator size="large" color={theme.colors.primary} style={{ marginTop: 40 }} />
        ) : exams.length === 0 ? (
          <View style={styles.emptyState}>
            <Ionicons name="calendar-outline" size={64} color={theme.colors.border} />
            <Text style={styles.emptyText}>Şu anda aktif bir sınavınız bulunmuyor.</Text>
          </View>
        ) : (
          <FlatList
            data={exams}
            renderItem={renderExamItem}
            keyExtractor={item => item.exam_id}
            contentContainerStyle={styles.listContent}
          />
        )}
      </View>
    </SafeAreaView>
  );
}

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
  headerTitle: { fontSize: 20, fontWeight: '700', color: theme.colors.text, fontFamily: 'Outfit_700Bold' },
  joinSection: { padding: theme.spacing.lg, backgroundColor: theme.colors.card, margin: theme.spacing.lg, borderRadius: 20 },
  sectionTitle: { fontSize: 16, fontWeight: '700', color: theme.colors.text, marginBottom: 16, fontFamily: 'Outfit_700Bold' },
  inputRow: { flexDirection: 'row', gap: 12 },
  input: { 
    flex: 1, 
    height: 50, 
    backgroundColor: theme.colors.background, 
    borderRadius: 12, 
    paddingHorizontal: 16,
    borderWidth: 1,
    borderColor: theme.colors.border,
    fontSize: 16,
    fontWeight: '700',
    color: theme.colors.primary,
    fontFamily: 'Outfit_700Bold'
  },
  joinBtn: { 
    backgroundColor: theme.colors.primary, 
    paddingHorizontal: 24, 
    borderRadius: 12, 
    justifyContent: 'center',
    alignItems: 'center'
  },
  disabledBtn: { opacity: 0.5 },
  joinBtnText: { color: 'white', fontWeight: '700', fontFamily: 'Outfit_700Bold' },
  listSection: { flex: 1, paddingHorizontal: theme.spacing.lg },
  listContent: { paddingBottom: 20 },
  examCard: { 
    flexDirection: 'row', 
    alignItems: 'center', 
    backgroundColor: theme.colors.card, 
    padding: 16, 
    borderRadius: 16, 
    marginBottom: 12,
    borderWidth: 1,
    borderColor: theme.colors.border
  },
  examIcon: { 
    width: 48, 
    height: 48, 
    borderRadius: 12, 
    backgroundColor: 'rgba(6, 133, 103, 0.1)', 
    alignItems: 'center', 
    justifyContent: 'center',
    marginRight: 16
  },
  examInfo: { flex: 1 },
  examTitle: { fontSize: 16, fontWeight: '600', color: theme.colors.text, marginBottom: 4, fontFamily: 'Outfit_600SemiBold' },
  examMeta: { fontSize: 13, color: theme.colors.textMuted, fontFamily: 'Outfit_400Regular' },
  emptyState: { alignItems: 'center', marginTop: 60 },
  emptyText: { marginTop: 16, color: theme.colors.textMuted, textAlign: 'center', fontSize: 14, fontFamily: 'Outfit_400Regular' }
});
