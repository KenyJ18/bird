import { Stack, TextField, Button, Grid } from '@mui/material';
import { useRouter } from 'next/router';
import React from 'react';
import { useForm } from 'react-hook-form';
import { useSetAtom } from 'jotai';
import { budgetAtom } from '@/atoms/budget';

type EnterFormValues = {
    budget: string;
};

export const Enter: React.FC = () => {
    const { register, handleSubmit, formState: { errors } } = useForm<EnterFormValues>();
    const router = useRouter();
    const setBudget = useSetAtom(budgetAtom);

    const onSubmit = (data: EnterFormValues) => {
        setBudget(Number(data.budget));
        router.push('/enter-check');
    };

    const handleCancel = () => {
        router.back();
    };

    return (
        <form onSubmit={handleSubmit(onSubmit)}>
            <h1>予算入力画面</h1>
            <div>
                <Stack direction="column" gap={2}>
                    <Grid container spacing={2}>
                        <TextField
                            label="予算" 
                            variant="outlined" 
                            fullWidth
                            {...register("budget", { required: true })}
                            type="number"
                            error={!!errors.budget}
                            helperText={errors.budget ? "予算は必須です" : ""}
                        />
                    </Grid>
                    <Grid container spacing={2}>
                        <Button variant="contained" color="primary" type="submit" fullWidth>次へ</Button>
                        <Button variant="contained" color="secondary" onClick={handleCancel} fullWidth>キャンセル</Button>
                    </Grid>
                </Stack>
            </div>
        </form>
    );
};

export default Enter;
