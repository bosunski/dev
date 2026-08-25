import type { Step, StepResolver } from '../../../types/step.js'
import { dnsConfigSchema, DnsConfig } from '../config/dns-config.js'
import { DnsStep } from '../steps/dns-step.js'
import { UserException } from '../../../exceptions.js'

export class DnsResolver implements StepResolver {
  resolve(args: unknown): Step {
    const result = dnsConfigSchema.safeParse(args)
    if (!result.success) throw new UserException(`Invalid DNS configuration: ${result.error.message}`)
    return new DnsStep(new DnsConfig(result.data))
  }
}
