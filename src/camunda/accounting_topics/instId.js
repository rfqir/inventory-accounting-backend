import client from '../client.js';
import { Variables } from 'camunda-external-task-client-js';
import { getMoOrder } from '../../graphql/query/listing.js';
import { insertMoOrderPrint } from '../../graphql/mutation/insertMoOrderPrint.js';


// Subscribe to the "prosesOrder" task from Camunda
client.subscribe('inst_id', async ({ task, taskService }) => {
  const proc_inst_id = task.processInstanceId;
  const score = 16;
  try {
    const variablesCamunda = new Variables();
    variablesCamunda.set("inst_id", proc_inst_id);
    variablesCamunda.set("score", score);
    await taskService.complete(task,variablesCamunda);
  } catch (error) {
    console.error('status: ' + error.status);
    console.error('data: ' + error.data);
  }
});
