import { z } from 'zod'

export type RawScript = {
  desc?: string | undefined
  name?: string | undefined
  run: string | string[]
  cwd?: string | undefined
  'met?'?: string | undefined
}

export type RawStep = Record<string, unknown> | RawScript

const promptArgsSchema = z.object({
  prompt: z.string(),
  label: z.string().optional(),
  placeholder: z.string().optional(),
  default: z.string().optional(),
  required: z.boolean().optional(),
  hint: z.string().optional(),
  type: z.enum(['password', 'text']).optional(),
})

const rawServeProcessSchema = z.union([
  z.string(),
  z.object({
    run: z.string(),
    env: z.union([z.string(), z.literal(false)]).optional(),
    cwd: z.string().optional(),
  }),
])

const rawRuntimeSchema = z.object({
  type: z.literal('php').optional(),
  version: z.string().optional(),
  provider: z.enum(['auto', 'native', 'spc']).optional(),
  server: z.literal('fpm').optional(),
  extensions: z.array(z.string().min(1).regex(/^[A-Za-z0-9_]+$/)).optional(),
})

export const rawConfigSchema = z.object({
  name: z.string().optional(),
  type: z.string().optional(),
  up: z.array(z.record(z.string(), z.unknown())).optional(),
  steps: z.array(z.record(z.string(), z.unknown())).optional(),
  commands: z.record(z.string(), z.object({
    desc: z.string().optional(),
    run: z.union([z.string(), z.array(z.string())]),
    cwd: z.string().optional(),
    signature: z.string().optional(),
  })).optional(),
  serve: z.union([z.string(), z.record(z.string(), z.union([
    rawServeProcessSchema,
    z.record(z.string(), rawServeProcessSchema),
  ]))]).optional(),
  groups: z.record(z.string(), z.array(z.string())).optional(),
  sites: z.record(z.string(), z.string()).optional(),
  env: z.record(z.string(), z.union([z.string(), z.number(), z.boolean(), promptArgsSchema])).optional(),
  projects: z.array(z.string()).optional(),
  runtimes: z.record(z.string(), rawRuntimeSchema).optional(),
}).passthrough()

export type PromptArgs = z.infer<typeof promptArgsSchema>
export type RawEnvValue = string | number | boolean | PromptArgs
export type RawServeProcess = z.infer<typeof rawServeProcessSchema>
export type RawServeGroup = Record<string, RawServeProcess>
export type RawServe = RawServeProcess | RawServeGroup
export type RawRuntime = z.infer<typeof rawRuntimeSchema>
export type RawConfig = z.infer<typeof rawConfigSchema>
export type RawCommand = NonNullable<RawConfig['commands']>[string]
