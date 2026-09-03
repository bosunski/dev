import type { StepResolver, Step } from '../../types/step.js'
import type { ValetPlugin } from './valet-plugin.js'
import { ValetStep } from './steps/valet-step.js'
import type { Dev } from '../../dev.js'
import { z } from 'zod'

const rawSiteSchema = z.union([z.string(), z.object({
  host: z.string(),
  proxy: z.string().optional(),
  secure: z.boolean().optional(),
})])
const rawExtensionConfigSchema = z.object({
  before: z.string().optional(),
  options: z.record(z.string(), z.string()).optional(),
})
const rawPhpSchema = z.object({
  version: z.union([z.string(), z.number()]).optional(),
  extensions: z.record(z.string(), rawExtensionConfigSchema.nullable()).optional(),
})
export const rawValetConfigSchema = z.object({
  php: z.union([z.string(), z.number(), rawPhpSchema]).optional(),
  sites: z.array(rawSiteSchema).optional(),
})

export class ValetStepResolver implements StepResolver {
  constructor(private readonly plugin: ValetPlugin, private readonly dev: Dev) {}

  resolve(args: unknown): Step {
    const valetBin = this.plugin.localConfig?.get('bin') ?? 'valet'
    return new ValetStep(
      rawValetConfigSchema.parse(args),
      valetBin,
      this.plugin.localConfig ?? undefined,
      this.dev,
    )
  }
}
