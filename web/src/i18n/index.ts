import { createI18n } from 'vue-i18n'
import cs from './cs.json'
import en from './en.json'
import sk from './sk.json'

// SK build: default UI language is Slovak (overridable via the switcher, persisted
// in localStorage). Falls back to Czech for any string not yet translated.
export const i18n = createI18n({
  legacy: false,
  locale: localStorage.getItem('locale') || 'sk',
  fallbackLocale: 'cs',
  messages: { cs, en, sk },
})
