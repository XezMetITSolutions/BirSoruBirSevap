import { View, Text, StyleSheet, Image, TouchableOpacity, Dimensions } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { theme } from '../theme';

const { width } = Dimensions.get('window');

export const HomeScreen = ({ navigation }) => {
  return (
    <SafeAreaView style={styles.container}>
      {/* Background blurs */}
      <View style={[styles.blur, styles.blur1]} />
      <View style={[styles.blur, styles.blur2]} />
      
      <View style={styles.content}>
        <View style={styles.logoWrapper}>
          <Image 
            source={require('../../assets/logo.png')}
            style={styles.logo}
          />
        </View>
        <Text style={styles.title}>Bir Soru Bir Sevap</Text>
        <Text style={styles.subtitle}>İlim Yolunda Bir Adım...</Text>
        <View style={styles.loaderLine} />
        
        <TouchableOpacity 
          style={styles.btn}
          onPress={() => navigation.navigate('BankSelection')}
        >
          <Text style={styles.btnText}>Hemen Başla</Text>
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
  blur: {
    position: 'absolute',
    width: 300,
    height: 300,
    borderRadius: 150,
    opacity: 0.1,
  },
  blur1: {
    top: -100,
    left: -100,
    backgroundColor: theme.colors.primary,
  },
  blur2: {
    bottom: -100,
    right: -100,
    backgroundColor: theme.colors.secondary,
  },
  content: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: theme.spacing.xl,
  },
  logoWrapper: {
    width: 140,
    height: 140,
    backgroundColor: 'white',
    borderRadius: 40,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: theme.spacing.xl,
    elevation: 20,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 10 },
    shadowOpacity: 0.3,
    shadowRadius: 20,
  },
  logo: {
    width: 100,
    height: 100,
    borderRadius: 20,
  },
  title: {
    fontSize: 32,
    fontWeight: '800',
    color: theme.colors.text,
    fontFamily: 'Outfit_800ExtraBold',
    letterSpacing: -1,
  },
  subtitle: {
    fontSize: 18,
    color: theme.colors.textMuted,
    marginTop: theme.spacing.sm,
    fontFamily: 'Outfit_400Regular',
  },
  loaderLine: {
    width: '40%',
    height: 4,
    backgroundColor: theme.colors.card,
    borderRadius: 2,
    marginTop: theme.spacing.xxl,
    overflow: 'hidden',
  },
  btn: {
    backgroundColor: theme.colors.primary,
    paddingVertical: 18,
    paddingHorizontal: 40,
    borderRadius: 20,
    marginTop: 60,
    width: '100%',
    alignItems: 'center',
    elevation: 10,
    shadowColor: theme.colors.primary,
    shadowOffset: { width: 0, height: 10 },
    shadowOpacity: 0.3,
    shadowRadius: 20,
  },
  btnText: {
    color: 'white',
    fontSize: 18,
    fontWeight: '700',
    fontFamily: 'Outfit_700Bold',
  },
});
