import React, { useState } from 'react';
import { View, Text, StyleSheet, TextInput, TouchableOpacity, ActivityIndicator, Alert, KeyboardAvoidingView, Platform, Image } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { theme } from '../theme';
import { API_ENDPOINTS } from '../api/config';
import { authStorage } from '../api/auth';

export const LoginScreen = ({ navigation }: any) => {
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);

  const handleLogin = async () => {
    if (!username || !password) {
      Alert.alert('Hata', 'Lütfen kullanıcı adı ve şifrenizi girin.');
      return;
    }

    setLoading(true);
    try {
      const response = await fetch(API_ENDPOINTS.LOGIN, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ username, password }),
      });

      const data = await response.json();

      if (data.success) {
        await authStorage.saveUser(data.user);
        navigation.replace('Dashboard');
      } else {
        Alert.alert('Giriş Başarısız', data.message || 'Hatalı kullanıcı adı veya şifre.');
      }
    } catch (e) {
      console.error(e);
      Alert.alert('Hata', 'Sunucuya bağlanırken bir sorun oluştu.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <SafeAreaView style={styles.container}>
      <KeyboardAvoidingView 
        behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
        style={styles.keyboardView}
      >
        <View style={styles.content}>
          <Image 
            source={require('../../assets/logo.png')} 
            style={styles.logo}
            resizeMode="contain"
          />
          <Text style={styles.title}>Hoş Geldiniz</Text>
          <Text style={styles.subtitle}>Eğitim portalına giriş yapın</Text>

          <View style={styles.inputGroup}>
            <Text style={styles.label}>Kullanıcı Adı</Text>
            <TextInput
              style={styles.input}
              placeholder="Username"
              placeholderTextColor={theme.colors.textMuted}
              value={username}
              onChangeText={setUsername}
              autoCapitalize="none"
            />
          </View>

          <View style={styles.inputGroup}>
            <Text style={styles.label}>Şifre</Text>
            <TextInput
              style={styles.input}
              placeholder="••••••••"
              placeholderTextColor={theme.colors.textMuted}
              value={password}
              onChangeText={setPassword}
              secureTextEntry
            />
          </View>

          <TouchableOpacity 
            style={styles.loginBtn}
            onPress={handleLogin}
            disabled={loading}
          >
            {loading ? (
              <ActivityIndicator color="white" />
            ) : (
              <Text style={styles.loginText}>Giriş Yap</Text>
            )}
          </TouchableOpacity>

          <TouchableOpacity 
            style={styles.guestBtn}
            onPress={() => navigation.replace('Home')}
          >
            <Text style={styles.guestText}>Misafir Olarak Devam Et</Text>
          </TouchableOpacity>
        </View>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.background,
  },
  keyboardView: {
    flex: 1,
  },
  content: {
    flex: 1,
    padding: theme.spacing.xl,
    justifyContent: 'center',
    alignItems: 'center',
  },
  logo: {
    width: 120,
    height: 120,
    marginBottom: 40,
  },
  title: {
    fontSize: 32,
    fontWeight: '800',
    color: theme.colors.text,
    fontFamily: 'Outfit_800ExtraBold',
    marginBottom: 8,
  },
  subtitle: {
    fontSize: 16,
    color: theme.colors.textMuted,
    fontFamily: 'Outfit_400Regular',
    marginBottom: 40,
  },
  inputGroup: {
    width: '100%',
    marginBottom: 20,
  },
  label: {
    color: theme.colors.text,
    fontSize: 14,
    marginBottom: 8,
    fontFamily: 'Outfit_600SemiBold',
    marginLeft: 4,
  },
  input: {
    width: '100%',
    backgroundColor: theme.colors.card,
    borderRadius: 16,
    padding: 16,
    color: theme.colors.text,
    borderWidth: 1,
    borderColor: theme.colors.border,
    fontFamily: 'Outfit_400Regular',
  },
  loginBtn: {
    width: '100%',
    backgroundColor: theme.colors.primary,
    borderRadius: 16,
    height: 56,
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: 20,
    shadowColor: theme.colors.primary,
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.3,
    shadowRadius: 8,
    elevation: 5,
  },
  loginText: {
    color: 'white',
    fontSize: 18,
    fontWeight: '700',
    fontFamily: 'Outfit_700Bold',
  },
  guestBtn: {
    marginTop: 24,
  },
  guestText: {
    color: theme.colors.textMuted,
    fontSize: 14,
    fontFamily: 'Outfit_500Medium',
    textDecorationLine: 'underline',
  },
});
