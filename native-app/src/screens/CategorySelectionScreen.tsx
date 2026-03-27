import React, { useEffect, useState } from 'react';
import { View, Text, StyleSheet, FlatList, TouchableOpacity, ActivityIndicator } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { theme } from '../theme';
import { API_ENDPOINTS } from '../api/config';

export const CategorySelectionScreen = ({ route, navigation }: any) => {
  const { bankId, bankTitle } = route.params;
  const [categories, setCategories] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchCategories();
  }, []);

  const fetchCategories = async () => {
    try {
      const response = await fetch(API_ENDPOINTS.MOBILE_INFO);
      const data = await response.json();
      setCategories(data.categories[bankId] || []);
      setLoading(false);
    } catch (e) {
      console.error(e);
      // Mock categories if API fails
      setCategories(['Ahlak', 'İbadet', 'Siyer', 'Hadis', 'Tefsir', 'Tasavvuf']);
      setLoading(false);
    }
  };

  const renderItem = ({ item }) => (
    <TouchableOpacity 
      style={styles.card}
      onPress={() => navigation.navigate('QuizSetup', { bankId, category: item })}
    >
      <Text style={styles.cardTitle}>{item}</Text>
      <Text style={styles.cardArrow}>→</Text>
    </TouchableOpacity>
  );

  return (
    <SafeAreaView style={styles.container}>
      <View style={styles.header}>
        <TouchableOpacity onPress={() => navigation.goBack()}>
          <Text style={styles.backBtn}>←</Text>
        </TouchableOpacity>
        <Text style={styles.headerTitle}>{bankTitle}</Text>
        <Text style={styles.headerSubtitle}>Lütfen bir konu seçin</Text>
      </View>

      {loading ? (
        <ActivityIndicator size="large" color={theme.colors.primary} style={styles.content} />
      ) : (
        <FlatList
          data={categories}
          renderItem={renderItem}
          keyExtractor={(item, index) => index.toString()}
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
    borderRadius: 20,
    padding: 20,
    marginBottom: 12,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  cardTitle: {
    fontSize: 18,
    fontWeight: '600',
    color: theme.colors.text,
    fontFamily: 'Outfit_600SemiBold',
  },
  cardArrow: {
    fontSize: 20,
    color: theme.colors.textMuted,
    fontFamily: 'Outfit_400Regular',
  },
});
