import { createVuetify } from 'vuetify'
import * as components from 'vuetify/components'
import * as directives from 'vuetify/directives'
import 'vuetify/styles'

export default createVuetify({
  components,
  directives,
  theme: {
    defaultTheme: 'light',
    themes: {
      light: {
        colors: {
          primary: '#1565C0',
          secondary: '#0D1B2A',
          accent: '#42A5F5',
          error: '#D32F2F',
          warning: '#F57C00',
          success: '#388E3C',
          background: '#F5F7FA',
        }
      }
    }
  }
})
