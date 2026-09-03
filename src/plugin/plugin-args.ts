import { z } from 'zod'
import { Dev } from '../dev.js'

const pluginArgsSchema = z.object({ dev: z.instanceof(Dev) })

export function pluginDev(args: Record<string, unknown>): Dev {
  return pluginArgsSchema.parse(args).dev
}
