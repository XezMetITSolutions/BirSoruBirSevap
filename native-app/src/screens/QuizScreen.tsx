import React, { useState, useEffect, useRef } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, ActivityIndicator, Alert, Animated, Dimensions } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { theme } from '../theme';
import { API_ENDPOINTS } from '../api/config';

export const QuizScreen = ({ route, navigation }: any) => {
  const { bankId, category, count, timer: hasTimer } = route.params;
  
  const [questions, setQuestions] = useState([]);
  const [loading, setLoading] = useState(true);
  const [currentIndex, setCurrentIndex] = useState(0);
  const [selectedOption, setSelectedOption] = useState(null);
  const [isAnswered, setIsAnswered] = useState(false);
  const [score, setScore] = useState(0);
  const [timeLeft, setTimeLeft] = useState(30);
  const [results, setResults] = useState([]);
  const [startTime] = useState(Date.now());
  
  const timerRef = useRef(null);
  const progressAnim = useRef(new Animated.Value(0)).current;

  useEffect(() => {
    fetchQuestions();
    return () => clearInterval(timerRef.current);
  }, []);

  const fetchQuestions = async () => {
    try {
      const response = await fetch(`${API_ENDPOINTS.QUESTIONS}?bank=${encodeURIComponent(bankId)}&category=${encodeURIComponent(category)}&count=${count}`);
      const data = await response.json();
      
      // Shuffle and prepare questions
      const prepared = data.map(q => {
        let options = q.options.map((text, index) => ({ text, index }));
        for (let i = options.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [options[i], options[j]] = [options[j], options[i]];
        }
        return {
            ...q,
            shuffledOptions: options,
            newCorrectIndex: options.findIndex(o => o.index === q.correct)
        };
      });

      setQuestions(prepared);
      setLoading(false);
      startQuestion();
    } catch (e) {
      console.error(e);
      // Fallback fallback questions if API fails
      setQuestions([
          { text: 'Örnek soru?', shuffledOptions: [{text: 'A', index: 0}, {text: 'B', index: 1}], newCorrectIndex: 0, options: ['A','B'] }
      ]);
      setLoading(false);
    }
  };

  const startQuestion = () => {
    setSelectedOption(null);
    setIsAnswered(false);
    if (hasTimer) {
      setTimeLeft(30);
      clearInterval(timerRef.current);
      timerRef.current = setInterval(() => {
        setTimeLeft(prev => {
          if (prev <= 1) {
            clearInterval(timerRef.current);
            submitAnswer(-1); // Timeout
            return 0;
          }
          return prev - 1;
        });
      }, 1000);
    }
    
    Animated.timing(progressAnim, {
      toValue: (currentIndex + 1) / questions.length,
      duration: 500,
      useNativeDriver: false
    }).start();
  };

  const submitAnswer = (optionIndex) => {
    if (isAnswered) return;
    
    clearInterval(timerRef.current);
    const question = questions[currentIndex];
    const isCorrect = optionIndex === question.newCorrectIndex;
    
    setSelectedOption(optionIndex);
    setIsAnswered(true);
    
    if (isCorrect) {
      setScore(prev => prev + 10);
    }
    
    const newResults = [...results, { 
      question: question.text, 
      isCorrect, 
      userAnswer: optionIndex === -1 ? 'Süre Doldu' : question.shuffledOptions[optionIndex].text,
      correctAnswer: question.shuffledOptions[question.newCorrectIndex].text
    }];
    setResults(newResults);
  };

  const nextQuestion = () => {
    if (currentIndex < questions.length - 1) {
      setCurrentIndex(prev => prev + 1);
      startQuestion();
    } else {
      const duration = Math.round((Date.now() - startTime) / 1000);
      navigation.navigate('Result', { score, total: questions.length, results, duration });
    }
  };

  if (loading) return <View style={styles.loading}><ActivityIndicator size="large" color={theme.colors.primary} /></View>;

  const currentQuestion = questions[currentIndex];

  return (
    <SafeAreaView style={styles.container}>
      <View style={styles.header}>
        <View style={styles.progressRow}>
          <View style={styles.progressBarBg}>
            <Animated.View style={[styles.progressFill, { width: progressAnim.interpolate({
              inputRange: [0, 1],
              outputRange: ['0%', '100%']
            }) }]} />
          </View>
          <Text style={styles.counter}>{currentIndex + 1} / {questions.length}</Text>
        </View>
        <View style={styles.timerCircle}>
          <Text style={styles.timerText}>{hasTimer ? timeLeft : '∞'}</Text>
        </View>
      </View>

      <View style={styles.content}>
        <View style={styles.questionCard}>
          <Text style={styles.questionText}>{currentQuestion.text}</Text>
        </View>

        <View style={styles.optionsGrid}>
          {currentQuestion.shuffledOptions.map((option, index) => {
            const isSelected = selectedOption === index;
            const isCorrect = index === currentQuestion.newCorrectIndex;
            let btnStyle = styles.optionBtn;
            if (isAnswered) {
              if (isCorrect) btnStyle = [styles.optionBtn, styles.optionCorrect];
              else if (isSelected) btnStyle = [styles.optionBtn, styles.optionWrong];
            } else if (isSelected) {
              btnStyle = [styles.optionBtn, styles.optionSelected];
            }

            return (
              <TouchableOpacity 
                key={index}
                style={btnStyle}
                onPress={() => submitAnswer(index)}
                disabled={isAnswered}
              >
                <View style={styles.letterBox}>
                  <Text style={styles.letterText}>{String.fromCharCode(65 + index)}</Text>
                </View>
                <Text style={styles.optionText}>{option.text}</Text>
              </TouchableOpacity>
            );
          })}
        </View>
      </View>

      <View style={styles.footer}>
        <TouchableOpacity style={styles.quitBtn} onPress={() => navigation.goBack()}>
          <Text style={styles.quitText}>✕</Text>
        </TouchableOpacity>
        <TouchableOpacity 
          style={[styles.nextBtn, !isAnswered && styles.disabledNext]}
          onPress={nextQuestion}
          disabled={!isAnswered}
        >
          <Text style={styles.nextText}>{currentIndex === questions.length - 1 ? 'Bitir' : 'Sonraki →'}</Text>
        </TouchableOpacity>
      </View>
    </SafeAreaView>
  );
};

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: theme.colors.background },
  loading: { flex: 1, justifyContent: 'center', backgroundColor: theme.colors.background },
  header: { padding: theme.spacing.lg, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  progressRow: { flex: 1, marginRight: 20 },
  progressBarBg: { height: 8, backgroundColor: theme.colors.card, borderRadius: 4, overflow: 'hidden', marginBottom: 6 },
  progressFill: { height: '100%', backgroundColor: theme.colors.primary },
  counter: { color: theme.colors.textMuted, fontSize: 12, fontFamily: 'Outfit_600SemiBold' },
  timerCircle: { width: 44, height: 44, borderRadius: 22, borderWidth: 2, borderColor: theme.colors.primary, alignItems: 'center', justifyContent: 'center' },
  timerText: { color: theme.colors.primary, fontWeight: '800', fontSize: 16, fontFamily: 'Outfit_700Bold' },
  content: { flex: 1, padding: theme.spacing.lg },
  questionCard: { backgroundColor: theme.colors.card, padding: 24, borderRadius: 24, borderLeftWidth: 5, borderLeftColor: theme.colors.secondary, marginBottom: 24, borderWidth: 1, borderColor: theme.colors.border },
  questionText: { fontSize: 20, fontWeight: '700', color: theme.colors.text, lineHeight: 28, fontFamily: 'Outfit_700Bold' },
  optionsGrid: { gap: 12 },
  optionBtn: { backgroundColor: theme.colors.card, padding: 16, borderRadius: 20, flexDirection: 'row', alignItems: 'center', borderWidth: 1, borderColor: theme.colors.border },
  optionSelected: { backgroundColor: theme.colors.primary, borderColor: theme.colors.primary },
  optionCorrect: { backgroundColor: theme.colors.success, borderColor: theme.colors.success },
  optionWrong: { backgroundColor: theme.colors.error, borderColor: theme.colors.error },
  letterBox: { width: 36, height: 36, backgroundColor: 'rgba(255,255,255,0.1)', borderRadius: 10, alignItems: 'center', justifyContent: 'center', marginRight: 12 },
  letterText: { color: 'white', fontWeight: '800', fontSize: 16, fontFamily: 'Outfit_800ExtraBold' },
  optionText: { flex: 1, color: theme.colors.text, fontSize: 16, fontWeight: '500', fontFamily: 'Outfit_500Medium' },
  footer: { padding: theme.spacing.lg, flexDirection: 'row', gap: 12 },
  quitBtn: { width: 64, height: 64, borderRadius: 20, backgroundColor: theme.colors.card, alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: theme.colors.border },
  quitText: { color: theme.colors.textMuted, fontSize: 24 },
  nextBtn: { flex: 1, height: 64, borderRadius: 20, backgroundColor: theme.colors.primary, alignItems: 'center', justifyContent: 'center' },
  disabledNext: { opacity: 0.3 },
  nextText: { color: 'white', fontSize: 18, fontWeight: '700', fontFamily: 'Outfit_700Bold' },
});
