import AsyncStorage from '@react-native-async-storage/async-storage';

export interface User {
  username: string;
  full_name: string;
  role: string;
  branch?: string;
  class_section?: string;
  region?: string;
}

const USER_KEY = '@user_data';

export const authStorage = {
  saveUser: async (user: User) => {
    try {
      await AsyncStorage.setItem(USER_KEY, JSON.stringify(user));
    } catch (e) {
      console.error('Error saving user', e);
    }
  },
  getUser: async (): Promise<User | null> => {
    try {
      const json = await AsyncStorage.getItem(USER_KEY);
      return json ? JSON.parse(json) : null;
    } catch (e) {
      console.error('Error getting user', e);
      return null;
    }
  },
  logout: async () => {
    try {
      await AsyncStorage.removeItem(USER_KEY);
    } catch (e) {
      console.error('Error during logout', e);
    }
  }
};
