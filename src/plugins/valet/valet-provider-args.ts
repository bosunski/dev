import { z } from 'zod'
import { Dev } from '../../dev.js'
import { ValetPlugin } from './valet-plugin.js'

const valetProviderArgsSchema = z.object({
  dev: z.instanceof(Dev),
  plugin: z.lazy(() => z.instanceof(ValetPlugin)),
})

export function valetProviderArgs(args: Record<string, unknown>): z.infer<typeof valetProviderArgsSchema> {
  return valetProviderArgsSchema.parse(args)
}
